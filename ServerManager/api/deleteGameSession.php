<?php

use ServerManager\API;
use ServerManager\GameSession;
use ServerManager\User;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Message\GameList\GameListArchiveMessage;

require __DIR__ . '/../init.php';

$api = new API;
$gamesession = new GameSession;
$user = new User();

$user->hasToBeLoggedIn();

$gamesession->id = $_POST['session_id'] ?? "";
$gamesession->delete(); // << altered to drop legacy server API call
SymfonyToLegacyHelper::getInstance()->getMessageBus()->dispatch(
    new GameListArchiveMessage($gamesession->id)
);

$api->setStatusSuccess();
$api->setPayload(["gamesession" => get_object_vars($gamesession)]);
$api->Return();
