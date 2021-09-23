<?php
require_once '../init.php'; 

$api = new API;
$gamesession = new GameSession;
$user = new User();

$user->hastobeLoggedIn();

// first check if the session_id referred to can even be obtained
$gamesession->id = $_POST["session_id"] ?? "";
$gamesession->get();

// now optionally change all the object vars
$gamesession->setJWT($_POST['jwt'] ?? "");
$gamesession->name = $_POST["name"] ?? $gamesession->name;
$gamesession->game_config_version_id = $_POST["game_config_version_id"] ?? $gamesession->game_config_version_id;
$gamesession->game_server_id = $_POST["game_server_id"] ?? $gamesession->game_server_id;
$gamesession->game_geoserver_id = $_POST["game_geoserver_id"] ?? $gamesession->game_geoserver_id;
$gamesession->watchdog_server_id = $_POST["watchdog_server_id"] ?? $gamesession->watchdog_server_id;
$gamesession->game_creation_time = $_POST["game_creation_time"] ?? $gamesession->game_creation_time;
$gamesession->game_start_year = $_POST["game_start_year"] ?? $gamesession->game_start_year;
$gamesession->game_end_month = $_POST["game_end_month"] ?? $gamesession->game_end_month;
$gamesession->game_current_month = $_POST["game_current_month"] ?? $gamesession->game_current_month;
$gamesession->game_running_til_time = $_POST["game_running_til_time"] ?? $gamesession->game_running_til_time;
$gamesession->password_admin = $_POST["password_admin"] ?? $gamesession->password_admin;
$gamesession->password_player = $_POST["password_player"] ?? $gamesession->password_player;
$gamesession->session_state = $_POST["session_state"] ?? $gamesession->session_state;
$gamesession->game_state = $_POST["game_state"] ?? $gamesession->game_state;
$gamesession->game_visibility = $_POST["game_visibility"] ?? $gamesession->game_visibility;
$gamesession->players_active = $_POST["players_active"] ?? $gamesession->players_active;
$gamesession->players_past_hour = $_POST["players_past_hour"] ?? $gamesession->players_past_hour;
$gamesession->demo_session = $_POST["demo_session"] ?? $gamesession->demo_session;
$gamesession->api_access_token = $_POST["api_access_token"] ?? $gamesession->api_access_token;
$gamesession->save_id = $_POST["save_id"] ?? $gamesession->save_id;
$gamesession->server_version = $_POST["server_version"] ?? $gamesession->server_version;

// then perform any allowed and existing object action requested
$allowed_actions = array(
    "setUserAccess", // called in JS function saveUserAccess
    "upgrade", // called in JS function callUpgrade
    "changeGameState", // called in JS functions startSession, pauseSession
    "processZip", // called by server API gamesession/ArchiveGameSessionInternal
    "recreate", // called by JS function RecreateSession
    "demoCheck" // called by server API game/Tick >> UpdateGameDetailsAtServerManager and by JS function toggleDemoSession
);
$action = $_POST["action"] ?? "";
if (method_exists($gamesession, $action) && in_array($action, $allowed_actions)) 
{
    $api->setPayLoad([$action => $gamesession->$action()]);
}

// ready to do final actual update
$gamesession->edit();
$api->setPayLoad(["gamesession" => get_object_vars($gamesession)]);
$api->setStatusSuccess();
$api->Return();

?>