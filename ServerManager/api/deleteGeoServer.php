<?php
require __DIR__ . '/../init.php';

$api = new API;
$geoserver = new GeoServer;
$user = new User();

$user->hastobeLoggedIn();

$geoserver->id = $_POST['geoserver_id'] ?? "";
$geoserver->delete();

$api->setStatusSuccess();
$api->setPayload(["geoserver" => get_object_vars($geoserver)]);
$api->Return();
