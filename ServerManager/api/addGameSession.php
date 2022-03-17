<?php
require_once '../init.php'; 

$api = new API;
$gamesession = new GameSession;
$user = new User();

$user->hastobeLoggedIn();

$gamesession->setJWT($_POST['jwt'] ?? "");
$gamesession->processPostedVars();
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