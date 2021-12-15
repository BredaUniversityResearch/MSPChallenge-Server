<?php
require_once __DIR__ . '/../init.php';

$api = new API;
$watchdog = new Watchdog;
$user = new User();

$user->hastobeLoggedIn();

$api->setPayload(["watchdogslist" => $watchdog->getList()]);
$api->setStatusSuccess();
$api->Return();

?>
