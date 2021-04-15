<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$api = new API;

$gamesession = new GameSession;
$id = $_POST["session_id"] ?? "";
$countries = explode(" ",$_POST["countries"]) ?? "";

$password_admin = $_POST['password_admin'] ?? "";
$password_region = $_POST['password_region'] ?? "";
$password_player = $_POST["password_player"] ?? array();
$password_playerall = $_POST["password_playerall"] ?? "";

$users_admin = $_POST['users_admin'] ?? "";
$users_region = $_POST['users_region'] ?? "";
$users_player = $_POST['users_player'] ?? array();
$users_playerall = $_POST['users_playerall'] ?? "";

$provider_admin = $_POST['provider_admin'] ?? "local";
$provider_region = $_POST['provider_region'] ?? "local";
$provider_player = $_POST['provider_player'] ?? "local";
$provider_admin_external = $_POST['provider_admin_external'] ?? "";
$provider_region_external = $_POST['provider_region_external'] ?? "";
$provider_player_external = $_POST['provider_player_external'] ?? "";

// set up the admin
if ($provider_admin == "external") $provider_admin = $provider_admin_external;
$admin["admin"]["provider"] = $provider_admin;
if ($provider_admin == "local") $admin["admin"]["password"] = $password_admin;
else $admin["admin"]["users"] = array_unique(explode(" ", trim(preg_replace('!\s+!', ' ', $users_admin)))); //trims, gets rid of multiple spaces & duplicate values

// set up the region manager
if ($provider_region == "external") $provider_region = $provider_region_external;
$admin["region"]["provider"] = $provider_region;
if ($provider_region == "local") $admin["region"]["password"] = $password_region;
else $admin["region"]["users"] = array_unique(explode(" ", trim(preg_replace('!\s+!', ' ', $users_region)))); //trims, gets rid of multiple spaces & duplicate values 
	
// set up all the players
if ($provider_player == "external") $provider_player = $provider_player_external;
$player["provider"] = $provider_player;
if ($provider_player == "local") {
	if (!empty($password_playerall)) {
		foreach ($countries as $team) {
			$player["password"][$team] = $password_playerall;
		}
	}
	else {
		foreach ($password_player as $team => $password) {
			$player["password"][$team] = $password;
		}
	}
}
else {
	if (!empty($users_playerall)) {
		foreach ($countries as $team) {
			$player["users"][$team] = array_unique(explode(" ", trim(preg_replace('!\s+!', ' ', $users_playerall)))); //trims, gets rid of multiple spaces & duplicate values 
		}
	}
	else {
		foreach ($users_player as $team => $users) {
			$player["users"][$team] = array_unique(explode(" ", trim(preg_replace('!\s+!', ' ', $users)))); //trims, gets rid of multiple spaces & duplicate values 
		}
	}
}

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
