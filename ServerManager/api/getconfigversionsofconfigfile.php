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
$query_string = " SELECT * FROM `game_config_version`" .
				" WHERE `game_config_files_id` = ? AND visibility = 'active'" .
				" ORDER BY `version` DESC";

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'This error should never show up....!';
$response_array['html_options'] = '';

// Forms posted
if(!empty($_POST['config_file_id'])){
	// Config file ID given
	$db->query($query_string, [$_POST['config_file_id']]);
	$response_array['status'] = 'success';
	$response_array['message'] = $_POST['config_file_id'];
} else {
	// list ALL config versions in the database
	$db->findAll("game_config_version");
	$response_array['status'] = 'success';
	$response_array['message'] = 'No config file ID given. Returning ALL config versions!';
}
$response_array['count'] = $db->count();
$response_array['configversionslist'] = $db->results(true);

foreach ($db->results(true) as $key => $value) {
	$next_option = '<option value="'.$value['id'].'">#'.$value['version'].': '.$value['version_message'].'</option>';
	$response_array['html_options'] .= $next_option;
}
echo json_encode($response_array);
?>
