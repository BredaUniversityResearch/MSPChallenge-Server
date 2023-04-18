<?php

use ServerManager\API;
use ServerManager\GeoServer;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$geoserver = new GeoServer;
$user = new User();

$user->hasToBeLoggedIn();

$api->setPayload(["geoserverslist" => $geoserver->getList()]);
$api->setStatusSuccess();
$api->Return();
