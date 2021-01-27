<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

header('Content-type: application/json');

$db = DB::getInstance();

// Forms posted
if(!empty($_POST['session_id'])){
	$session_id = (int) $_POST['session_id'];
	$type = $_POST['type'] ?? 'archive';
	if ($type == 'archive') {
		$file = "../session_archive/session_archive_".$session_id.".zip";
	}
	elseif ($type == 'full' || $type == 'layers') {
		$db->query("SELECT * FROM game_saves WHERE id = ?", array($session_id));
		$thissave = $db->results(true);
		$file = "../".$thissave[0]["save_path"];
	}
	$response_array['status'] = 'success'; // because the check itself will succeed
	$response_array['message'] = ''; // because the check itself will succeed
	// build the filename string
	
	// check if the file exists
	if (file_exists($file)) {
		// also check if the file is more than 0 bits in size
		if (filesize($file) > 0) {
			$response_array['archiveavailable'] = true;
		}
		else {
			$response_array['archiveavailable'] = false;
		}
	}
	else {
		$response_array['archiveavailable'] = false;
	}
}
else {
	$response_array['status'] = 'error';
	$response_array['message'] = 'No session ID given.';
	$response_array['archiveavailable'] = false;
}

echo json_encode($response_array);
?>
