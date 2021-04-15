<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$return_array = array();
$db = DB::getInstance();

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'This error should never show up....!';

//get any uploaded save file
$uploadedSaveFile = $_FILES['uploadedSaveFile']['tmp_name'] ?? '';

if (!empty($uploadedSaveFile)) {
    //open to read game_list.json
    $zip = new ZipArchive;
    $res = $zip->open($uploadedSaveFile);
    if ($res === TRUE) {
        $game_list_json = $zip->getFromName('game_list.json');
        if ($game_list_json !== false) {
            $savevars = json_decode($game_list_json, true);
            $savevars["save_path"] = time(); // totally random, this is temporary (see the update further below)
            if (!isset($savevars["server_version"])) $savevars["server_version"] = "4.0-beta7";
            // add to the game_saves table
            if ($db->query("INSERT INTO game_saves (name, game_config_version_id, game_config_files_filename, game_config_versions_region, game_server_id, watchdog_server_id, 
                                game_creation_time, game_start_year, game_end_month, game_current_month, game_running_til_time, password_admin,
                                password_player, session_state, game_state, game_visibility, players_active, players_past_hour,
                                demo_session, api_access_token, save_type, save_path, server_version) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);", 
                                    array(ensure_unique_name($savevars['name'], "name", "game_saves"), $savevars['game_config_version_id'], $savevars['game_config_files_filename'], $savevars['game_config_versions_region'], $savevars['game_server_id'], $savevars['watchdog_server_id'], $savevars['game_creation_time'], 
                                    $savevars['game_start_year'], $savevars['game_end_month'], $savevars['game_current_month'], $savevars['game_running_til_time'], $savevars['password_admin'],
                                    $savevars['password_player'], $savevars['session_state'], $savevars['game_state'], $savevars['game_visibility'], $savevars['players_active'], $savevars['players_past_hour'],
                                    $savevars['demo_session'], $savevars['api_access_token'], "full", $savevars["save_path"], $savevars["server_version"]))) {
                
                // now, finally, make the filename unique and update game_saves table accordingly
                $save_id = $db->lastid();
                $newzipname = "saves/save_".$save_id.".zip";
                if (move_uploaded_file($uploadedSaveFile, ServerManager::getInstance()->GetServerManagerRoot().$newzipname)) {
                    if ($db->query("UPDATE game_saves SET save_path = ? WHERE id = ?", array($newzipname, $save_id))) {
                        $response_array["status"] = "success";
                        $response_array['message'] = "Your save file has been uploaded and is ready to be used.";
                    }
                    else {
                        $response_array['message'] = 'Could not properly save the new file name.';
                    }
                }
                else {
                    $response_array['message'] = 'Could not store the uploaded save file.';
                }
            }
            else {
                $response_array['message'] = 'Could not add save to the database.';
            }
        }
        else {
            $response_array['message'] = 'Could not find the game_list.json file in the ZIP file. This probably means that the ZIP file you selected is not an MSP Challenge Full Session Save ZIP file.';
        }
        $zip->close();
    }
    else {
        $response_array['message'] = 'Failed to open the ZIP.';
    }
}
echo json_encode($response_array);

?>