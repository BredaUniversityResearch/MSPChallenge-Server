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
$servermanager->processPostedVars();

// ready to do final actual update
$servermanager->edit();
$api->setPayLoad(["servermanager" => get_object_vars($servermanager)]);
$api->setStatusSuccess();
$api->Return();
