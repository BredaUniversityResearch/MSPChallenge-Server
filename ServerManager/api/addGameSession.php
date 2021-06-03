<?php
require_once '../init.php'; 

$api = new API;
$gamesession = new GameSession;
$user = new User();

$user->hastobeLoggedIn();

$gamesession->setJWT($_POST['jwt'] ?? "");
$gamesession->name = $_POST["name"] ?? "";
$gamesession->game_config_version_id = $_POST["game_config_version_id"] ?? "";
$gamesession->game_geoserver_id = $_POST["game_geoserver_id"] ?? "";
$gamesession->watchdog_server_id = $_POST["watchdog_server_id"] ?? "";
$gamesession->password_admin = $_POST["password_admin"] ?? "";
$gamesession->password_player = $_POST["password_player"] ?? "";
$gamesession->id = -1;
$gamesession->game_server_id = 1;
$gamesession->game_creation_time = time();
$gamesession->game_start_year = 0;
$gamesession->game_end_month = 0;
$gamesession->game_current_month = 0;
$gamesession->game_running_til_time = time();
$gamesession->session_state = 'request';
$gamesession->game_state = 'setup';
$gamesession->game_visibility = 'public';
$gamesession->players_active = 0;
$gamesession->players_past_hour = 0;
$gamesession->demo_session = 0;
$gamesession->api_access_token = 0;
$gamesession->save_id = 0;
$gamesession->server_version = ServerManager::getInstance()->GetCurrentVersion();

$gamesession->add();
$gamesession->sendCreateRequest();

$api->setStatusSuccess();
$api->setPayload(["gamesession" => get_object_vars($gamesession)]);
$api->Return();

?>