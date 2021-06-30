<?php
require_once '../init.php'; 

$api = new API;
$servermanager = ServerManager::getInstance();
$user = new User();

$user->hastobeLoggedIn();

$servermanager->get();

// optionally change all the object vars
$servermanager->setJWT($_POST['jwt'] ?? "");
$servermanager->server_name = $_POST['server_name'] ?? $servermanager->server_name;
$servermanager->server_address = $_POST['server_address'] ?? $servermanager->server_address;
$servermanager->server_description = $_POST['server_description'] ?? $servermanager->server_description;

// ready to do final actual update
$servermanager->edit();
$api->setPayLoad(["servermanager" => get_object_vars($servermanager)]);
$api->setStatusSuccess();
$api->Return();

?>