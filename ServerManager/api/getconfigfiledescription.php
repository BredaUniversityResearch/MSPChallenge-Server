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

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'This error should never show up....!';

// Forms posted
if(!isset($_POST['config_file_id'])){
	// Config file ID given
	$response_array['message'] = 'Unknown config file ID';
}
else
{
	$db->query("SELECT description FROM game_config_files WHERE id = ?", [$_POST['config_file_id']]);
	$response_array['description'] = $db->results(true)[0]["description"];
	$response_array['status'] = 'success';
}
echo json_encode($response_array);
?>
