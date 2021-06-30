<?php
require_once '../init.php'; 

$api = new API;
$watchdog = new Watchdog;
$user = new User();

$user->hastobeLoggedIn();

$watchdog->name = $_POST['name'] ?? "";
$watchdog->address = $_POST['address'] ?? "";
$watchdog->available = 1;

$watchdog->add();

$api->setStatusSuccess();
$api->setPayload(["watchdog" => get_object_vars($watchdog)]);
$api->Return();

?>
