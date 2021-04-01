<?php
require_once '../init.php'; 

//$user->hastobeLoggedIn();

$api = new API;

$gamesession = new GameSession;
$id = $_POST["session_id"] ?? "";

$password_admin = $_POST['password_admin'] ?? "";
$password_region = $_POST['password_region'] ?? "";
$password_player = $_POST["password_player"] ?? array();

$users_admin = $_POST['users_admin'] ?? array();
$users_region = $_POST['users_region'] ?? array();
$users_player = $_POST['users_player'] ?? array();

$provider_admin = $_POST['provider_admin'] ?? "local";
$provider_region = $_POST['provider_region'] ?? "local";
$provider_player = $_POST['provider_player'] ?? "local";

// set up the admin
$admin["admin"]["provider"] = $provider_admin;
if ($provider_admin == "local")
{
	$admin["admin"]["password"] = $password_admin;
} else
{	
	foreach ($users_admin as $key => $user)
	{
		$admin["admin"]["users"][] = $user;
	}
}
// set up the region manager
$admin["region"]["provider"] = $provider_admin;
if ($provider_region == "local")
{
	$admin["region"]["password"] = $password_region;
} else
{	
	foreach ($users_admin as $key => $user)
	{
		$admin["region"]["users"][] = $user;
	}
}
// set up all the players
$player["provider"] = $provider_player;
if ($provider_player == "local")
{
	foreach ($password_player as $team => $password)
	{
		$player["password"][$team] = $password;
	}
} else
{	
	foreach ($users_player as $team => $users)
	{
		$player["users"][$team] = $users;
	}
}
die(var_dump($player));
$set = $gamesession->setUserAccess($id, $admin, $player);

if ($set !== true)
{
	$api->setStatusFailure();
	$api->setMessage($set);
} else 
{
	$api->setStatusSuccess();
	$api->setMessage("User access was successfully saved.");
}

$api->printReturn();

?>
