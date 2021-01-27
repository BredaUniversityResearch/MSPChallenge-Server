<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

// some try...catch should be included here...
$db = DB::getInstance();
$db->findAll("game_list");
$sessionslist = $db->results(true);

header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'No session ID given.';

// Forms posted
if(!empty($_GET['session_id'])){
	// session ID given, but stil unknown
	$session_id = $_GET['session_id'];
	$response_array['message'] = 'Unknown session ID given.';
	// let's look for the given session id in our sessions list
	foreach ($sessionslist as $key => $value) {
		if($value['id'] == $session_id) {
			// found it; let's see if the game is already running or archived
			switch ($value['session_state']) {
				case 'archived':
					$response_array['status'] = 'success';
					$response_array['message'] = 'Downloading the archived session with the following ID: '.$session_id;

					$storeFilePath = GetSessionArchiveBaseDirectory()."session_archive_".$session_id.".zip";
					header('Content-Type: application/x-download');
					header("Content-Disposition: attachment; filename=".basename($storeFilePath).";");
					header('Content-Length: ' . filesize($storeFilePath));
					readfile($storeFilePath);
					return;

					break;
				default:
					$response_array['status'] = 'error';
					$response_array['message'] = 'The session with the ID "'.$session_id.'" is not archived!';
					break;
			}
		}
	}
}

echo json_encode($response_array);
?>
