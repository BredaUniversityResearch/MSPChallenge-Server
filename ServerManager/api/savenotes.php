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

if(isset($_POST['save_id']) && isset($_POST['notes']))
{
	$db = DB::getInstance();

	$notes = strip_tags($_POST['notes']);
	if (is_numeric($_POST['save_id']))
	{
		$saveId = intval($_POST['save_id']);
		$sql = "UPDATE game_saves SET save_notes = ? WHERE id = ?";
		$params = array($notes, $saveId);
		$db->query($sql, $params);	
		$response_array['status'] = "Success";
		$response_array['message'] = "The Server Save's notes were successfully stored.";
	}	
}
echo json_encode($response_array);
?>
