<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

$return_array = array();
// some try...catch should be included here...
$db = DB::getInstance();
$query_string = "SELECT id, address, name FROM game_watchdog_servers";

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'This error should never show up....!';
$response_array['html_options'] = '';

if ($db->query($query_string)) {
	$response_array['status'] = 'success';
	$response_array['message'] = '';
	$response_array['count'] = $db->count();

	$results = $db->results(true);

	$response_array['watchdoglist'] = $results;
} else {
	$response_array['status'] = 'error';
	$response_array['message'] = $db->errorString();
	$response_array['count'] = 0;
	$response_array['watchdoglist'] = array();
}

echo json_encode($response_array);
?>
