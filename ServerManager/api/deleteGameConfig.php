<?php

use ServerManager\API;
use ServerManager\GameConfig;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$gameconfig = new GameConfig;
$user = new User();

$user->hasToBeLoggedIn();

$gameconfig->id = $_POST['config_version_id'] ?? "";
$gameconfig->delete();

$api->setStatusSuccess();
$api->setPayload(["gameconfig" => get_object_vars($gameconfig)]);
$api->Return();
