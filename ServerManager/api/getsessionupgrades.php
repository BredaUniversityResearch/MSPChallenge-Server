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
    $server_version = $db->cell("game_list.server_version", ["id", "=", $session_id]);
    if (!empty($server_version)) {
        $response_array['status'] = 'success';
        $response_array['message'] = '';
        $response_array['upgrade'] = ServerManager::getInstance()->CheckForUpgrade($server_version);
    }
    else {
        $response_array['message'] = 'Could not get server version of session from database.';
    }
}

echo json_encode($response_array);

?>