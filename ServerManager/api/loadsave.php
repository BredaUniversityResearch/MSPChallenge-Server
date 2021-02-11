<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

//$user->hastobeLoggedIn();

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
                                            demo_session, api_access_token, save_id) 
                                    SELECT ? AS name, 0 AS game_config_version_id, game_server_id, ? AS watchdog_server_id, game_creation_time, game_start_year,
                                            game_end_month, game_current_month, game_running_til_time, password_admin,
                                            password_player, 'request', game_state, game_visibility, players_active, players_past_hour, 
                                            demo_session, api_access_token, id
                                            FROM game_saves WHERE game_saves.id = ?;",
                                    array($newServerName, $watchdogServer, $SaveFileSelector));
        $session_id = $db->lastId();
    }
    else {
        //otherwise an existing server is going to be overwritten
        $session_id = $ExistingOrNewServerId;
    }

    
    
    $getsavedetails = $db->get("game_saves", ["id", "=", $SaveFileSelector]);
    $savedetails = $getsavedetails->results();
    $save_path = $abs_app_root.$url_app_root.$savedetails[0]->save_path;
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
            $dirtocheck = GetServerRasterBaseDirectory().$session_id."/";
            if (is_dir($dirtocheck)) {
                rrmdir($dirtocheck);
            }
            if (mkdir($dirtocheck)) {
                $alldone = mkdir($dirtocheck."archive/");
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
                        $destination = GetServerConfigBaseDirectory().$newconfigfilename;
                        $configsuccess = copy($source, $destination);
                    }
                    //put the raster folder and its files and dirs contents
                    elseif (strstr($fileinZIP, "raster/") !== false) {
                        $sourcedetails = $zip->statIndex($i);
                        if ($sourcedetails["size"] > 0) {   // this seems to be the most effective way of determining that we're dealing with an actual file
                            $destination = GetServerRasterBaseDirectory().$session_id."/".str_replace("raster/", "", $fileinZIP);
                            $rastersuccess[] = copy($source, $destination);
                        }
                    }
                    //import the database
                    elseif (strstr($fileinZIP, "db_export_") !== false) {
                        $destination = $abs_app_root.$url_app_root."saves/".$fileinZIP;
                        if (copy($source, $destination)) {
                            $databaseHost = Config::get('mysql/host');
                            $databaseUser = Config::get('mysql/username');
                            $dbPassword =	Config::get('mysql/password');
                            $databaseName = Config::get('mysql/multisession_database_prefix').$session_id;
                            $db->query("DROP DATABASE IF EXISTS ".$databaseName."; CREATE DATABASE ".$databaseName.";");
                            $db->query("SELECT @@basedir as mysql_home");
                            $mysqlDirQuery = $db->results(true);
                            $mysqlDir = $mysqlDirQuery[0]["mysql_home"];
                            $dumpCommand = $mysqlDir."/bin/mysql --user=\"".$databaseUser."\" --password=\"".$dbPassword."\" --host=\"".$databaseHost."\" \"".$databaseName."\" < \"".$abs_app_root.$url_app_root."saves/".$fileinZIP."\"";
                            exec($dumpCommand);
                            $dbasesuccess = true;
                            unlink($abs_app_root.$url_app_root."saves/".$fileinZIP);
                        }
                    }
                    else {
                        //ignoring all other files in the zip at this point
                    }
                }
                if ($configsuccess && $dbasesuccess && !in_array(false, $rastersuccess)) {
                    // SetGameSessionValues to update the watchdog
                    if (!empty($watchdogServer)) {
                        $watchdog_address = $db->cell("game_watchdog_servers.address", ["id", "=", $watchdogServer]);
                        $api_url = Config::get('msp_server_protocol').$db->cell("game_servers.address", ["id", "=", 1]).Config::get('code_branch')."/".$session_id."/api/GameSession/ResetWatchdogAddress";
                        CallAPI("POST", $api_url, array("watchdog_address" => $watchdog_address));
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