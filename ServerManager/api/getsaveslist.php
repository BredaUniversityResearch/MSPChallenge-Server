<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

$allowed_visibilities = array('active','archived');

header('Content-type: application/json');
$return_array = array();
$visibility = 'active';
$response_array['status'] = 'success';
$response_array['message'] = '';
$where_array = array();

// some try...catch should be included here...
$db = DB::getInstance();
$query_string = "SELECT gs.id, gs.save_timestamp, gs.name, gs.game_config_files_filename as filename, gs.save_type,
				DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(gs.game_start_year as char),'-01-01') , '%Y-%m-%d') , INTERVAL + gs.game_current_month MONTH),'%M %Y' ) as game_current_month
				FROM game_saves gs 
				WHERE gs.save_visibility = ?;";

// Forms posted
if(!empty($_POST)) {
	if(!empty($_POST['visibility'])){
		// visibility given, let's make sure it's valid
		$visibility = $_POST['visibility'];
		$allowed = in_array($visibility, $allowed_visibilities);
			if(!$allowed){
				$visibility = 'active';
				$response_array['message'] = 'Unknown visibility given. Using default value (active).';
			}
	}
} else {
	$response_array['message'] = 'No visibility given. Using default value (active).';
}

if ($db->query($query_string, array($visibility))) {
	$response_array['status'] = 'success';
	$response_array['message'] = '';
	$response_array['count'] = $db->count();
	$results = $db->results(true);
	$response_array['saveslist'] = $results;
} else {
	$response_array['status'] = 'error';
	$response_array['message'] = $db->errorString();
	$response_array['count'] = 0;
	$response_array['saveslist'] = array();
}

echo json_encode($response_array);
?>
