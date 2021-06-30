<?php
require_once '../init.php'; 

$api = new API;
$watchdog = new Watchdog;
$user = new User();

$user->hastobeLoggedIn();

// first check if the watchdog id referred to can even be obtained
$watchdog->id = $_POST["watchdog_id"] ?? "";
$watchdog->get();

// now optionally change all the object vars
$watchdog->name = $_POST['name'] ?? $watchdog->name;
$watchdog->address = $_POST['address'] ?? $watchdog->address;

// ready to do final actual update
$watchdog->edit();
$api->setPayLoad(["watchdog" => get_object_vars($watchdog)]);
$api->setStatusSuccess();
$api->Return();

?>