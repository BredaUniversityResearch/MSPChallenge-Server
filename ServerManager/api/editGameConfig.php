<?php

use ServerManager\API;
use ServerManager\GameConfig;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$gameconfig = new GameConfig;
$user = new User();

$user->hasToBeLoggedIn();

// first check if the config_version_id referred to can even be obtained
$gameconfig->id = $_POST["config_version_id"] ?? "";
$gameconfig->get();

// now optionally change all the object vars
$gameconfig->processPostedVars();

// ready to do final actual update
$gameconfig->edit();
$api->setPayLoad(["gameconfig" => get_object_vars($gameconfig)]);
$api->setStatusSuccess();
$api->Return();
