<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';

require_once './testdata.php'; */

$user->hastobeLoggedIn();

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'No session ID given.';
$response_array['sessioninfo'] = array();
$session_info = array();

$db = DB::getInstance();
$query_string = "SELECT games.*,
					servers.name AS game_server_name,
					servers.address AS game_server_address,
				  	watchdogs.name AS watchdog_name,
					watchdogs.address AS watchdog_address,
				  	config_versions.version AS config_version_version,
					config_versions.version_message AS config_version_message,
				  	config_files.filename AS config_file_name,
					config_files.description AS config_file_description,
					saves.game_config_files_filename,
					DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(games.game_start_year as char),'-01-01') , '%Y-%m-%d') , INTERVAL + games.game_current_month MONTH),'%M %Y' ) as current_month_formatted,
 					DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(games.game_start_year  as char),'-01-01') , '%Y-%m-%d') , INTERVAL + games.game_end_month MONTH),'%M %Y' ) as end_month_formatted
				  FROM `game_list` AS games
				  	LEFT JOIN `game_servers` AS servers ON games.`game_server_id` = servers.`id`
				  	LEFT JOIN `game_watchdog_servers` AS watchdogs ON games.`watchdog_server_id` = watchdogs.`id`
				  	LEFT JOIN `game_config_version` AS config_versions ON games.`game_config_version_id` = config_versions.`id`
				  	LEFT JOIN `game_config_files` AS config_files ON config_versions.`game_config_files_id` = config_files.`id`
				  	LEFT JOIN `game_saves` AS saves ON saves.`id` = games.`save_id`
				  WHERE games.`id` = ?";

// Forms posted
if(!empty($_POST['session_id'])){
	// session ID given, but stil unknown
	$session_id = $_POST['session_id'];
	$response_array['message'] = 'Unknown session ID given.';
	if ($db->query($query_string, [$session_id])) {
		$response_array['status'] = 'success';
		$response_array['message'] = $session_id;

		$sessionInfo = $results = TransformGameList($db->results(true))[0];
		$sessionInfo["game_creation_time"] = UnixToReadableTime($sessionInfo["game_creation_time"]);
		$sessionInfo["game_running_til_time"] = UnixToReadableTime($sessionInfo["game_running_til_time"]);
		$sessionInfo["log"] = getSessionLog($session_id);
		
		$response_array['sessioninfo'] = $sessionInfo;
	}
}


function getSessionLog($sessionId) {
	try {
		$log_dir = "../log";
		$logPrefix = 'log_session_';
		$logFileName =  $log_dir.'/'. $logPrefix . $sessionId . '.log';
		$handle = fopen($logFileName, "r");
		$contents = fread($handle, filesize($logFileName));
		fclose($handle);
		return nl2br($contents);

	} catch (Exception $e) {
		return "Log not found.";
	}

}

echo json_encode($response_array);
?>
