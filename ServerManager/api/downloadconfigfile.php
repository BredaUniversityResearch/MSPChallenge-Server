<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$response_array = array('status' => 'error',
	'message' => 'Missing parameter values'
);

if(isset($_GET['version_id']))
{
	$db = DB::getInstance();
	if (is_numeric($_GET['version_id']))
	{
		$configFileId = intval($_GET['version_id']);
		$db->query("SELECT game_config_version.file_path
			FROM game_config_version
			WHERE game_config_version.id = ?",
			array($configFileId));
		$results = $db->results(true);
		if (count($results) == 0)
		{
			$response_array['message'] = "Unknown config version id";
			die(json_encode($response_array));
		}
		$storeFilePath = ServerManager::getInstance()->GetConfigBaseDirectory().$results[0]['file_path'];

		header('Content-Type: application/x-download');
		header("Content-Disposition: attachment; filename=".basename($storeFilePath).";");
		header('Content-Length: ' . filesize($storeFilePath));
		readfile($storeFilePath);
		return;
	}

	$response_array['status'] = "Success";
	$response_array['message'] = "";
}
echo json_encode($response_array);

?>
