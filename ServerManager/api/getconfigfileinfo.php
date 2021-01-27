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
	$db->query("SELECT game_config_version.id,
			game_config_version.version,
			game_config_version.version_message,
			game_config_version.upload_time,
			game_config_version.last_played_time,
			game_config_version.visibility,
			game_config_files.filename,
			game_config_files.description,
			IF(game_config_version.upload_user='1','BUas (at installation)',users.username) AS upload_user
		FROM game_config_version
			INNER JOIN game_config_files ON game_config_version.game_config_files_id = game_config_files.id
			LEFT JOIN users ON game_config_version.upload_user = users.id
		WHERE game_config_version.id = ?", array($_POST['config_file_version_id']));

	$result = $db->results(true)[0];
	$result["upload_time"] = UnixToReadableTime($result["upload_time"]);
  if ($result["last_played_time"] == 0) $result["last_played_time"] = "Never";
	else $result["last_played_time"] = UnixToReadableTime($result["last_played_time"]);

	$response_array['config_file_info'] = $result;
	$response_array['status'] = 'success';
}
echo json_encode($response_array);
?>
