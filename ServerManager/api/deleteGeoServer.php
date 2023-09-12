<?php

use ServerManager\API;
use ServerManager\GeoServer;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$geoserver = new GeoServer;
$user = new User();

$user->hasToBeLoggedIn();

$geoserver->id = $_POST['geoserver_id'] ?? "";
$geoserver->delete();

$api->setStatusSuccess();
$api->setPayload(["geoserver" => get_object_vars($geoserver)]);
$api->Return();
