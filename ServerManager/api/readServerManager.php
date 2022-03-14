<?php
require __DIR__ . '/../init.php';

$api = new API;
$servermanager = ServerManager::getInstance();
$user = new User();

$user->hastobeLoggedIn();

$servermanager->get();

$api->setPayload(["servermanager" => get_object_vars($servermanager)]);
$api->setStatusSuccess();
$api->Return();
