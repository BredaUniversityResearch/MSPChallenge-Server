<?php

use ServerManager\API;
use ServerManager\User;
use ServerManager\Watchdog;

require __DIR__ . '/../init.php';

$api = new API;
$watchdog = new Watchdog;
$user = new User();

$user->hasToBeLoggedIn();

$api->setPayload(["watchdogslist" => $watchdog->getList()]);
$api->setStatusSuccess();
$api->Return();
