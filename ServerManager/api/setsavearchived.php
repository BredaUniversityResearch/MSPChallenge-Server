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
	$response_array['message'] = 'Unknown save ID';
}
else
{
	$newVisiblity = filter_var($_POST['archived_state'], FILTER_VALIDATE_BOOLEAN)? 'archived' : 'active';
	$currentpath = $db->cell("game_saves.save_path", ["id", "=", $_POST['save_id']]);
	$newpath = str_replace("saves/", "saves/archive/", $currentpath);
	if (rename($abs_app_root.$url_app_root.$currentpath, $abs_app_root.$url_app_root.$newpath)) {
		$db->query("UPDATE game_saves SET save_visibility = ?, save_path = ? WHERE id = ?", array($newVisiblity, $newpath, $_POST['save_id']));
		$response_array['message'] = 'Server Save changed to '.$newVisiblity.'.';
		$response_array['status'] = 'success';
	}
}
echo json_encode($response_array);
?>
