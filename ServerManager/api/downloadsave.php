<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

$response_array = array('status' => 'error',
	'message' => 'Missing parameter values'
);

if(isset($_GET['id']))
{
	$db = DB::getInstance();
	if (is_numeric($_GET['id']))
	{
		$saveId = intval($_GET['id']);
		$db->query("SELECT save_path
			FROM game_saves
			WHERE id = ?",
			array($saveId));
		$results = $db->results(true);
		if (count($results) == 0)
		{
			$response_array['message'] = "Unknown save id";
			die(json_encode($response_array));
		}
		$storeFilePath = '../'.$results[0]['save_path'];

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
