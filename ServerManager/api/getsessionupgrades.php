<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$db = DB::getInstance();

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'No session ID given.';
$response_array['upgrade'] = false;

// first get the session's creation date
if(!empty($_POST['session_id'])) {
    $session_id = $_POST['session_id'];
    // then get upgrade info from ServerManager class
    $game_creation_time = $db->cell("game_list.game_creation_time", ["id", "=", $session_id]);
    if (!empty($game_creation_time)) {
        // first rehash $game_creation_time into the int that ServerManager wants
        $game_creation_time = (new DateTime("@".$game_creation_time))->format("Ymd");
        $response_array['status'] = 'success';
        $response_array['message'] = '';
        $response_array['upgrade'] = ServerManager::getInstance()->CheckForUpgrade((int) $game_creation_time);
    }
    else {
        $response_array['message'] = 'Could not get creation time of session from database.';
    }
}

echo json_encode($response_array);

?>