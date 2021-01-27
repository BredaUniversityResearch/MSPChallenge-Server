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
$query_string = "SELECT game_config_version.*,
					game_config_files.filename AS config_file_name,
					game_config_files.description AS config_file_description,
					IF(game_config_version.upload_user='1','BUas (at installation)',users.username) AS upload_user
				FROM game_config_version
					INNER JOIN game_config_files ON game_config_version.game_config_files_id = game_config_files.id
					LEFT JOIN users ON game_config_version.upload_user = users.id
				WHERE game_config_version.visibility = ?
				ORDER BY game_config_files.filename ASC, game_config_version.upload_time DESC;";
	//AND ((game_config_version.game_config_files_id, game_config_version.version) IN (SELECT game_config_files_id, MAX(version) FROM game_config_version GROUP BY game_config_files_id))

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
	$db->query("SELECT game_config_files_id, MAX(version) AS numversions FROM game_config_version GROUP BY game_config_files_id;");
	$numversions = $db->results(true);
	foreach ($numversions as $key => $value) {
		$numversionslist[$value["game_config_files_id"]] = $value["numversions"];
	}
	
	foreach($results as &$result) {
		$result['upload_time'] = UnixToReadableTime($result['upload_time']);
		if ($result["last_played_time"] == 0) $result["last_played_time"] = "Never";
		else $result['last_played_time'] = UnixToReadableTime($result['last_played_time']);
	}

	$response_array['configversionslist'] = $results;
	$response_array['numversionslist'] = $numversionslist;
} else {
	$response_array['status'] = 'error';
	$response_array['message'] = $db->errorString();
	$response_array['count'] = 0;
	$response_array['configversionslist'] = array();
	$response_array['numversionslist'] = array();
}

echo json_encode($response_array);
?>
