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
$query_string = "SELECT id, description, filename
									FROM game_config_files
									WHERE (SELECT COUNT(game_config_version.id) FROM game_config_version WHERE game_config_version.game_config_files_id = game_config_files.id AND game_config_version.visibility = 'active') > 0";

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'This error should never show up....!';
$response_array['html_options'] = '';

$visibility = isset($_POST['config_visibility'])? $_POST['config_visibility'] : "active";
if ($db->query($query_string, array($visibility))) {
	$response_array['status'] = 'success';
	$response_array['message'] = '';
	$response_array['count'] = $db->count();

	$results = $db->results(true);

	$response_array['configlist'] = $results;
} else {
	$response_array['status'] = 'error';
	$response_array['message'] = $db->errorString();
	$response_array['count'] = 0;
	$response_array['configlist'] = array();
}

echo json_encode($response_array);
?>
