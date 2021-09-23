<?php
require_once '../init.php'; 

$api = new API;
$geoserver = new GeoServer;
$user = new User();

$user->hastobeLoggedIn();

$geoserver->setJWT($_POST['jwt'] ?? "");

$api->setPayload(["geoserverslist" => $geoserver->getList()]);
$api->setStatusSuccess();
$api->Return();

?>
