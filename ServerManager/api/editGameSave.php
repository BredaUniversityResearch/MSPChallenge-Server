<?php
require_once '../init.php'; 

$api = new API;
$gamesave = new GameSave;
$user = new User();

$user->hastobeLoggedIn();

// first check if the save_id referred to can even be obtained
$gamesave->id = $_POST["save_id"] ?? "";
$gamesave->get();

// now optionally change all the object vars
$gamesave->name = $_POST["name"] ?? $gamesave->name;
$gamesave->game_config_version_id = $_POST["game_config_version_id"] ?? $gamesave->game_config_version_id;
$gamesave->game_config_files_filename = $_POST["game_config_files_filename"] ?? $gamesave->game_config_files_filename;
$gamesave->game_config_versions_region = $_POST["game_config_versions_region"] ?? $gamesave->game_config_versions_region;
$gamesave->game_server_id = $_POST["game_server_id"] ?? $gamesave->game_server_id;
$gamesave->watchdog_server_id = $_POST["watchdog_server_id"] ?? $gamesave->watchdog_server_id;
$gamesave->game_creation_time = $_POST["game_creation_time"] ?? $gamesave->game_creation_time;
$gamesave->game_start_year = $_POST["game_start_year"] ?? $gamesave->game_start_year;
$gamesave->game_end_month = $_POST["game_end_month"] ?? $gamesave->game_end_month;
$gamesave->game_current_month = $_POST["game_current_month"] ?? $gamesave->game_current_month;
$gamesave->game_running_til_time = $_POST["game_running_til_time"] ?? $gamesave->game_running_til_time;
$gamesave->password_admin = $_POST["password_admin"] ?? $gamesave->password_admin;
$gamesave->password_player = $_POST["password_player"] ?? $gamesave->password_player;
$gamesave->session_state = $_POST["session_state"] ?? $gamesave->session_state;
$gamesave->game_state = $_POST["game_state"] ?? $gamesave->game_state;
$gamesave->game_visibility = $_POST["game_visibility"] ?? $gamesave->game_visibility;
$gamesave->players_active = $_POST["players_active"] ?? $gamesave->players_active;
$gamesave->players_past_hour = $_POST["players_past_hour"] ?? $gamesave->players_past_hour;
$gamesave->demo_session = $_POST["demo_session"] ?? $gamesave->demo_session;
$gamesave->api_access_token = $_POST["api_access_token"] ?? $gamesave->api_access_token;
$gamesave->server_version = $_POST["server_version"] ?? $gamesave->server_version;
$gamesave->save_type = $_POST["save_type"] ?? $gamesave->save_type;
$gamesave->save_visibility = $_POST["save_visibility"] ?? $gamesave->save_visibility;
$gamesave->save_notes = $_POST["save_notes"] ?? $gamesave->save_notes;

// then perform any allowed and existing object action requested
$allowed_actions = array(
    "processZip" // called by server API gamesession/CreateGameSessionZip and gamesession/CreateGameSessionLayersZip
);
$action = $_POST["action"] ?? "";
if (method_exists($gamesave, $action) && in_array($action, $allowed_actions)) 
{
    $api->setPayLoad([$action => $gamesave->$action()]);
}

// ready to do final actual update
$gamesave->edit();
$api->setPayLoad(["gamesave" => get_object_vars($gamesave)]);
$api->setStatusSuccess();
$api->Return();

?>