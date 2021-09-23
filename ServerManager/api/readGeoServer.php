<?php
require_once '../init.php'; 

$api = new API;
$geoserver = new GeoServer;
$user = new User();

$user->hastobeLoggedIn();

$geoserver->id = $_POST['geoserver_id'] ?? 0;
$geoserver->get();

$api->setPayload(["geoserver" => get_object_vars($geoserver)]);
$api->setStatusSuccess();
$api->Return();

?>
