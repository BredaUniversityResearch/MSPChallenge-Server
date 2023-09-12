<?php

use ServerManager\API;
use ServerManager\GameSave;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$gamesave = new GameSave;
$user = new User();

$user->hasToBeLoggedIn();

$gamesave->id = $_POST['save_id'] ?? "";
$gamesave->delete();

$api->setStatusSuccess();
$api->setPayload(["gamesave" => get_object_vars($gamesave)]);
$api->Return();
