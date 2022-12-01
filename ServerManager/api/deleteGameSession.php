<?php

use ServerManager\API;
use ServerManager\GameSession;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$gamesession = new GameSession;
$user = new User();

$user->hasToBeLoggedIn();

$gamesession->id = $_POST['session_id'] ?? "";
$gamesession->delete();

$api->setStatusSuccess();
$api->setPayload(["gamesession" => get_object_vars($gamesession)]);
$api->Return();
