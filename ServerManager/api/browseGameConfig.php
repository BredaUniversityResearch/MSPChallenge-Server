<?php

use ServerManager\API;
use ServerManager\GameConfig;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$gameconfig = new GameConfig;
$user = new User();

$user->hasToBeLoggedIn();

$visibility = $_POST['visibility'] ?? 'active';
$where_array = array("visibility", "=", $visibility);

$api->setPayload(["configslist" => $gameconfig->getList($where_array)]);
$api->setStatusSuccess();
$api->Return();
