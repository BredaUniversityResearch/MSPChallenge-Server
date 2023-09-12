<?php

use ServerManager\API;
use ServerManager\ServerManager;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$servermanager = ServerManager::getInstance();
$user = new User();

$user->hasToBeLoggedIn();

$servermanager->get();

$api->setPayload(["servermanager" => get_object_vars($servermanager)]);
$api->setStatusSuccess();
$api->Return();
