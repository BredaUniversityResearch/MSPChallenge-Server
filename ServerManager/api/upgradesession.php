<?php
require_once '../init.php'; 

//$user->hastobeLoggedIn();

$db = DB::getInstance();

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'No session ID given.';

// first get the session's creation date
if(!empty($_POST['session_id'])) {
    $session_id = $_POST['session_id'];
    // then get upgrade info from ServerManager class
    $game_creation_time = $db->cell("game_list.game_creation_time", ["id", "=", $session_id]);
    if (!empty($game_creation_time)) {
        // first rehash $game_creation_time into the int that ServerManager wants
        $game_creation_time = (new DateTime("@".$game_creation_time))->format("Ymd");
        $upgrade = ServerManager::getInstance()->CheckForUpgrade((int) $game_creation_time);
        if ($upgrade !== false) {
            // ok, so let's call the server API!
            //get the correct token header for server API requests later on
            $additionalHeaders = array(GetGameSessionAPIAuthenticationHeader($session_id));
            $api_url = ServerManager::getInstance()->GetServerURLBySessionId($session_id)."/api/update/".$upgrade;
            $return = json_decode(CallAPI("POST", $api_url, array(), $additionalHeaders, false));
            if ($return->success) {
                if ($db->query("UPDATE game_list SET game_creation_time = ? WHERE id = ?", array(time(), $session_id))) {
                    $response_array['status'] = 'success';
                    $response_array['message'] = 'Session upgraded successfully.';
                }
                else {
                    $response_array['message'] = 'Session upgraded successfully, but the ServerManager database could not be updated accordingly.';
                }
            }
            else $response_array['message'] = $return->message;
        }
        else {
            $response_array['message'] = 'No upgrade available.';
        }
    }
    else {
        $response_array['message'] = 'Could not get creation time of session from database.';
    }
}

echo json_encode($response_array);

?>