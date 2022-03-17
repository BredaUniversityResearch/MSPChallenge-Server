<?php
require_once '../init.php'; 

$api = new API;
$watchdog = new Watchdog;
$user = new User();

$user->hastobeLoggedIn();

$watchdog->id = $_POST['watchdog_id'] ?? 0;
$watchdog->get();

$api->setPayload(["watchdog" => get_object_vars($watchdog)]);
$api->setStatusSuccess();
$api->Return();

?>
