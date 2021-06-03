<?php

require_once "browseGameSession.php";

/*
require_once '../init.php';
$user = new User();
$allowed_visibilities = array('public','archived');

header('Content-type: application/json');
$return_array = array();
$visibility = 'public';
$response_array['status'] = 'success';
$response_array['message'] = '';
$where_array = array();

$db = DB::getInstance();

$query_string = " SELECT games.id, games.name, games.game_config_version_id, games.game_server_id, games.watchdog_server_id," .
					" games.game_creation_time, games.game_start_year, games.game_end_month, games.game_current_month, games.game_running_til_time," .
					" games.session_state, games.game_state, games.game_visibility, games.players_active, games.players_past_hour," .
					" servers.name AS game_server_name, IF(servers.address='localhost', CONCAT('".Config::get("msp_server_protocol")."', '".$_SERVER["HTTP_HOST"]."', '".Config::get("code_branch")."'), CONCAT('".Config::get("msp_server_protocol")."', servers.address, '".Config::get("code_branch")."')) AS game_server_address," .
					" watchdogs.name AS watchdog_name, watchdogs.address AS watchdog_address, games.save_id, saves.game_config_files_filename, saves.game_config_versions_region, " .
					" config_versions.version AS config_version_version, config_versions.version_message AS config_version_message," .
					" config_files.filename AS config_file_name, config_files.description AS config_file_description, ".
					" config_versions.region AS region, " .
					" DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(games.game_start_year as char),'-01-01') , '%Y-%m-%d') , INTERVAL + games.game_current_month MONTH),'%M %Y' ) as current_month_formatted, ".
					" DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(games.game_start_year  as char),'-01-01') , '%Y-%m-%d') , INTERVAL + games.game_end_month MONTH), '%M %Y' ) as end_month_formatted	".
					" FROM game_list AS games" .
					" LEFT JOIN game_servers AS servers ON games.game_server_id = servers.id" .
					" LEFT JOIN game_watchdog_servers AS watchdogs ON games.watchdog_server_id = watchdogs.id" .
					" LEFT JOIN game_config_version AS config_versions ON games.game_config_version_id = config_versions.id" .
					" LEFT JOIN game_config_files AS config_files ON config_versions.game_config_files_id = config_files.id" .
					" LEFT JOIN game_saves AS saves ON saves.id = games.save_id";

// Forms posted
if(!empty($_POST)) {
	if(!empty($_POST['visibility'])){
		// visibility given, let's make sure it's valid
		$visibility = $_POST['visibility'];
		$allowed = in_array($visibility, $allowed_visibilities);
			if(!$allowed){
				$visibility = 'public';
				$response_array['message'] = 'Unknown visibility given. Using default value (public).';
			}
	}
} else {
	$response_array['message'] = 'No visibility given. Using default value (public).';
}

switch($visibility) {
	case 'public':
	case 'private':
		$query_string .= " WHERE games.`game_visibility` = ? AND NOT games.`session_state` = ?";
		$where_array = [$visibility, 'archived' ];
		break;
	case 'archived':
		$query_string .= " WHERE games.`session_state` = ?";
		$where_array = ['archived'];
		break;
	default:
		// yeah... just in case...
		$query_string .= " WHERE games.`game_visibility` = ? AND NOT games.`session_state` = ?";
		$where_array = ['public', 'archived'];
		break;
}

if (isset($_POST['demo_servers']) && $_POST['demo_servers'] == 1)
{
	$query_string .= " AND games.demo_session = 1";
}

if ($db->query($query_string, $where_array)) {
	$results = $db->results(true);
	$results = TransformGameList($results);
	if (!isset($_POST['client_timestamp'])) $results = YouShouldUpdate();
	$response_array['status'] = 'success';
	$response_array['message'] = $visibility;
	$response_array['count'] = $db->count();
	$response_array['sessionslist'] = $results;

} else {
	$response_array['status'] = 'error';
	$response_array['message'] = $db->errorString();
	$response_array['count'] = 0;
	$response_array['sessionslist'] = array();
}

echo json_encode($response_array);



function YouShouldUpdate() {
	$return[0] = array('id'=> 0,
						'name' => 'You are using a version of MSP Challenge incompatible with this server.',
						'session_state' => 'archived',
						'region' => 'none');
	$return[1] = array('id'=> 0,
						'name' => 'Please download and install '.ServerManager::getInstance()->GetCurrentVersion().' from www.mspchallenge.info.',
						'session_state' => 'archived',
						'region' => 'none');
	return $return;
}



*/
?>
