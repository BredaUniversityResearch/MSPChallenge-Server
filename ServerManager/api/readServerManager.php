<?php
require_once '../init.php'; 

$api = new API;
$servermanager = ServerManager::getInstance();
$user = new User();

// security disabled as the client can use this endpoint too - $user->hastobeLoggedIn();

$servermanager->get();

$api->setPayload(["servermanager" => get_object_vars($servermanager)]);
$api->setStatusSuccess();
$api->Return();

?>
