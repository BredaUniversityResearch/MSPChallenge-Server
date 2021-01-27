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
$response_array['status'] = 'error';
$response_array['message'] = 'Incomplete data.';

if (isset($_POST["name"]) && isset($_POST["address"]))
{
	$db = DB::getInstance();
	$result = $db->query("SELECT id FROM game_watchdog_servers WHERE name LIKE ? OR address LIKE ?", array($_POST["name"], $_POST["address"]));
	if ($result->count())
	{
		$response_array["status"] = "error";
		$response_array["message"] = "Duplicate server found in database. Please change name and/or address";
	}
	else
	{
		$db->query("INSERT INTO game_watchdog_servers (name, address) VALUES (?, ?)", array($_POST["name"], $_POST["address"]));
		$response_array["status"] = "success";
		$response_array["message"] = "Server has been added";
	}
}

echo json_encode($response_array);

?>
