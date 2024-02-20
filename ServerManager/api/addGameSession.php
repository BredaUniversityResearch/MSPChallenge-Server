<?php

use App\Domain\Services\SymfonyToLegacyHelper;
use App\Message\GameList\GameListCreationMessage;
use ServerManager\API;
use ServerManager\GameSession;
use ServerManager\ServerManager;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$gamesession = new GameSession;
$user = new User();

$user->hasToBeLoggedIn();

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
$gamesession->server_version = ServerManager::getInstance()->getCurrentVersion();

$gamesession->add();
//$gamesession->sendCreateRequest();
// alternative to the above:
SymfonyToLegacyHelper::getInstance()->getMessageBus()->dispatch(new GameListCreationMessage($gamesession->id));

$api->setStatusSuccess();
$api->setPayload(["gamesession" => get_object_vars($gamesession)]);
$api->Return();
