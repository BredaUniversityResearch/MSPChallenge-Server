<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

// some try...catch should be included here...
$db = DB::getInstance();

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'No session ID given.';

// Forms posted
if(!empty($_POST['session_id'])){
	$sessionId = intval($_POST['session_id']);

	$db->query("SELECT demo_session FROM game_list WHERE id = ?", array($sessionId));

	if ($db->count())
	{
		$isCurrentlyDemoSession = $db->results(true)[0]['demo_session'] == 1;

		$db->query("UPDATE game_list SET demo_session = ? WHERE id = ?", array($isCurrentlyDemoSession? 0 : 1, $sessionId));

		$response_array["status"] = "success";
		$response_array["message"] = "Succesfully toggled the demo state of server ".$sessionId." to ".($isCurrentlyDemoSession? "false" : "true");
	}
	else
	{
		$response_array["message"] = "Could not find server with specified ID";
	}
}

print(json_encode($response_array));
