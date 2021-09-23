<?php
require_once '../init.php'; 

$api = new API;
$geoserver = new GeoServer;
$user = new User();

$user->hastobeLoggedIn();

$geoserver->name = $_POST['name'] ?? "";
$geoserver->address = $_POST['address'] ?? "";
$geoserver->username = base64_encode($_POST['username']) ?? ""; // only exception to the rule of having the class encode it, this is because we never decode it again in the ServerManager
$geoserver->password = base64_encode($_POST['password']) ?? "";
$geoserver->available = 1;

$geoserver->add();

$api->setStatusSuccess();
$api->setPayload(["geoserver" => get_object_vars($geoserver)]);
$api->Return();

?>
