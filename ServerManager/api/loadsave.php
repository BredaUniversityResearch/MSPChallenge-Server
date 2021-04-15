<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$return_array = array();
$db = DB::getInstance();

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'This error should never show up....!';

//get the save id to use
$SaveFileSelector = $_POST['SaveFileSelector'] ?? 0;

//get the server id to pinpoint
$ExistingOrNewServerId = $_POST['ExistingOrNewServerId'] ?? 0;

//get the new server name
$newServerName = $_POST['newServerName'] ?? '';

//get the new watchdog ID
$watchdogServer = $_POST['newWatchdogLoadSave'] ?? 1;

if (empty($SaveFileSelector) || empty($ExistingOrNewServerId)) {
    $response_array['message'] = 'SaveFileSelector and ExistingOrNewServerId are required.';
}
else {
    if ($SaveFileSelector == -1) {
        // a recreate of an existing loaded save is requested, so we need to get the already known save_id from the database
        if ($ExistingOrNewServerId == -1) die(); // cannot handle that
        $SaveFileSelector = $db->cell("game_list.save_id", ["id", "=", $ExistingOrNewServerId]);
    }
    
    if ($ExistingOrNewServerId == -1) {
        // a new server is requested... 
        $db->query("INSERT INTO game_list (name, game_config_version_id, game_server_id, watchdog_server_id, game_creation_time, game_start_year,
                                            game_end_month, game_current_month, game_running_til_time, password_admin,
                                            password_player, session_state, game_state, game_visibility, players_active, players_past_hour, 
                                            demo_session, api_access_token, save_id, server_version) 
                                    SELECT ? AS name, 0 AS game_config_version_id, game_server_id, ? AS watchdog_server_id, game_creation_time, game_start_year,
                                            game_end_month, game_current_month, game_running_til_time, password_admin,
                                            password_player, 'request', game_state, game_visibility, players_active, players_past_hour, 
                                            demo_session, api_access_token, id, server_version
                                            FROM game_saves WHERE game_saves.id = ?;",
                                    array($newServerName, $watchdogServer, $SaveFileSelector));
        $session_id = $db->lastId();
    }
    else {
        //otherwise an existing server is going to be overwritten (recreated)
        $session_id = $ExistingOrNewServerId;
        $db->query("UPDATE game_list SET password_admin = (SELECT password_admin FROM game_saves WHERE game_saves.id = ?), 
                                        password_player = (SELECT password_player FROM game_saves WHERE game_saves.id = ?), 
                                        server_version = (SELECT server_version FROM game_saves WHERE game_saves.id = ?)
                                    WHERE game_list.id = ?;",
                                    array($SaveFileSelector, $SaveFileSelector, $SaveFileSelector, $session_id));
    }

    //get the correct token header for server API requests later on
    $additionalHeaders = array(GetGameSessionAPIAuthenticationHeader($session_id));
    
    $getsavedetails = $db->get("game_saves", ["id", "=", $SaveFileSelector]);
    $savedetails = $getsavedetails->results();
    $save_path = ServerManager::getInstance()->GetServerManagerRoot().$savedetails[0]->save_path;
    // now read and use the save ZIP
    if (file_exists($save_path) && mime_content_type($save_path) == "application/zip") {
        //it should be a ZIP and contain exactly 3 things: db_export_X.sql, session_config_X.json, and a raster folder
        $zip = new ZipArchive;
        $res = $zip->open($save_path);
        if ($res === TRUE) {
            set_time_limit(600);
            // ready to get going, lock it up
            $response = GameSessionStateChanger::ChangeSessionState($session_id, GameSessionStateChanger::STATE_PAUSE); //don't check the return, this is just a precaution
            //empty the old raster folder if it exists, create the folders again (start from scratch)
            $alldone = false;
            $dirtocheck = ServerManager::getInstance()->GetServerRasterBaseDirectory().$session_id;
            if (is_dir($dirtocheck)) {
                rrmdir($dirtocheck);
            }
            if (mkdir($dirtocheck)) {
                $alldone = mkdir($dirtocheck."/archive");
            }
            if ($alldone) {
                $configsuccess = false;
                $rastersuccess = array();
                $dbasesuccess = false;
                for($i = 0; $i < $zip->numFiles; $i++) {
                    $fileinZIP = $zip->getNameIndex($i);
                    $source = "zip://".$save_path."#".$fileinZIP;
                    //put the session_config_X.json
                    if (strstr($fileinZIP, "session_config_") !== false) {
                        $newconfigfilename = "session_config_".$session_id.".json";
                        $destination = ServerManager::getInstance()->GetServerConfigBaseDirectory().$newconfigfilename;
                        $configsuccess = copy($source, $destination);
                    }
                    //put the raster folder and its files and dirs contents
                    elseif (strstr($fileinZIP, "raster/") !== false) {
                        $sourcedetails = $zip->statIndex($i);
                        if ($sourcedetails["size"] > 0) {   // this seems to be the most effective way of determining that we're dealing with an actual file
                            $destination = ServerManager::getInstance()->GetServerRasterBaseDirectory().$session_id."/".str_replace("raster/", "", $fileinZIP);
                            $rastersuccess[] = copy($source, $destination);
                        }
                    }
                    //import the database
                    elseif (strstr($fileinZIP, "db_export_") !== false) {
                        $destination = ServerManager::getInstance()->GetSessionSavesBaseDirectory().$fileinZIP;
                        if (copy($source, $destination)) {
                            $databaseHost = Config::get('mysql/host');
                            $databaseUser = Config::get('mysql/username');
                            $dbPassword =	Config::get('mysql/password');
                            $databaseName = Config::get('mysql/multisession_database_prefix').$session_id;
                            $db->query("DROP DATABASE IF EXISTS ".$databaseName."; CREATE DATABASE ".$databaseName.";");
                            $db->query("SELECT @@basedir as mysql_home");
                            $mysqlDirQuery = $db->results(true);
                            $mysqlDir = $mysqlDirQuery[0]["mysql_home"];
                            $dumpCommand = $mysqlDir."/bin/mysql --user=\"".$databaseUser."\" --password=\"".$dbPassword."\" --host=\"".$databaseHost."\" \"".$databaseName."\" < \"".ServerManager::getInstance()->GetSessionSavesBaseDirectory().$fileinZIP."\"";
                            exec($dumpCommand);
                            $dbasesuccess = true;
                            unlink(ServerManager::getInstance()->GetSessionSavesBaseDirectory().$fileinZIP);
                        }
                    }
                    else {
                        //ignoring all other files in the zip at this point
                    }
                }
                if ($configsuccess && $dbasesuccess && !in_array(false, $rastersuccess)) {
                    $watchdog_address = $db->cell("game_watchdog_servers.address", ["id", "=", $watchdogServer]);
                    if (!empty($watchdog_address)) {
                        
                        $api_url = ServerManager::getInstance()->GetServerURLBySessionId($session_id)."/api/GameSession/ResetWatchdogAddress";
                        CallAPI("POST", $api_url, array("watchdog_address" => $watchdog_address), $additionalHeaders, false);
                    }
                    if (!empty($newconfigfilename)) {
                        $api_url2 = ServerManager::getInstance()->GetServerURLBySessionId($session_id)."/api/Game/Setupfilename";
                        CallAPI("POST", $api_url2, array("configFilename" => $newconfigfilename), $additionalHeaders, false);
                    }
                    
                    $db->query("UPDATE game_list SET session_state = 'healthy' WHERE id = ?", array($session_id));
                    $response_array['message'] = 'Save successfully loaded as server ID '.$session_id.'. Now available for use.';
                    $response_array['status'] = 'success';
                }
                else {
                    $response_array['message'] = 'Save unsuccessful: Config copy was '.$configsuccess.'; Database import was '.$dbasesuccess.'; Raster folder copy was '.!in_array(false, $rastersuccess);
                }
            }
            else {
                $response_array['message'] = 'Save unsuccessful: could not create the raster folders.';
            }
            // close the zip
            $zip->close();
        }
        else {
            $response_array['message'] = 'Could not open the save ZIP file.';
        }
    }
    else {
        $response_array['message'] = 'Could not find the save file.';
    }
}
echo json_encode($response_array);