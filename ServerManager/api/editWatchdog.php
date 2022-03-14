<?php
require __DIR__ . '/../init.php';

$api = new API;
$watchdog = new Watchdog;
$user = new User();

$user->hastobeLoggedIn();

// first check if the watchdog id referred to can even be obtained
$watchdog->id = $_POST["watchdog_id"] ?? "";
$watchdog->get();

// now optionally change all the object vars
$watchdog->processPostedVars();

// ready to do final actual update
$watchdog->edit();
$api->setPayLoad(["watchdog" => get_object_vars($watchdog)]);
$api->setStatusSuccess();
$api->Return();
