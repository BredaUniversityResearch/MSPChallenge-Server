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
if(!isset($_POST['save_id'])){
	// Save ID given
	$response_array['message'] = 'Unknown Save ID';
}
else
{
	$db->query("SELECT gs.id, gs.save_timestamp, gs.name, game_config_files_filename as filename, gs.save_type, gs.save_notes, gs.save_visibility,
	DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(gs.game_start_year as char),'-01-01') , '%Y-%m-%d') , INTERVAL + gs.game_current_month MONTH),'%M %Y' ) as game_current_month
	FROM game_saves gs 
	WHERE gs.id = ?;", array($_POST['save_id']));

	$result = $db->results(true)[0];
	$response_array['saveinfo'] = $result;
	$response_array['status'] = 'success';
}
echo json_encode($response_array);
?>
