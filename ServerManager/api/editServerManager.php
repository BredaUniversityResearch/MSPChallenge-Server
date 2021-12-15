<?php
require_once __DIR__ . '/../init.php';

$api = new API;
$servermanager = ServerManager::getInstance();
$user = new User();

$user->hastobeLoggedIn();

$servermanager->get();

// optionally change all the object vars
$servermanager->setJWT($_POST['jwt'] ?? "");
$servermanager->processPostedVars();

// ready to do final actual update
$servermanager->edit();
$api->setPayLoad(["servermanager" => get_object_vars($servermanager)]);
$api->setStatusSuccess();
$api->Return();

?>