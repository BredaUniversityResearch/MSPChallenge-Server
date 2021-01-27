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
$db = DB::getInstance();

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'This error should never show up....!';

// Forms posted
if(!isset($_POST['config_file_version_id'])){
	// Config file ID given
	$response_array['message'] = 'Unknown config file ID';
}
else
{
	$newVisiblity = filter_var($_POST['archived_state'], FILTER_VALIDATE_BOOLEAN)? 'archived' : 'active';

	$db->query("UPDATE game_config_version SET game_config_version.visibility = ?
		WHERE game_config_version.id = ?", array($newVisiblity, $_POST['config_file_version_id']));

	$response_array['message'] = 'Configuration file changed to '.$newVisiblity.'.';
	$response_array['status'] = 'success';
}
echo json_encode($response_array);
?>
