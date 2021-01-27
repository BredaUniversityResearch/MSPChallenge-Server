<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'No session ID given.';
$can_change = false;

// Forms posted
if(!empty($_POST['session_id'])){
	// session ID given??
	$response_array = GameSessionStateChanger::ChangeSessionState($_POST['session_id'], GameSessionStateChanger::STATE_PAUSE);
}

echo json_encode($response_array);
?>
