<?php
require __DIR__ . '/../init.php';

$api = new API;
$watchdog = new Watchdog;
$user = new User();

$user->hastobeLoggedIn();

$watchdog->processPostedVars();
$watchdog->available = 1;

$watchdog->add();

$api->setStatusSuccess();
$api->setPayload(["watchdog" => get_object_vars($watchdog)]);
$api->Return();
