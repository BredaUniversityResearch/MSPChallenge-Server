<?php

use ServerManager\API;
use ServerManager\GeoServer;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$geoserver = new GeoServer;
$user = new User();

$user->hasToBeLoggedIn();

// not using processPostedVars() here because of exception below
$geoserver->name = $_POST['name'] ?? "";
$geoserver->address = $_POST['address'] ?? "";
// only exception to the rule of having the class encode it, this is because we never decode it again in the
//   ServerManager
$geoserver->username = base64_encode($_POST['username']);
$geoserver->password = base64_encode($_POST['password']);
$geoserver->available = 1;

$geoserver->add();

$api->setStatusSuccess();
$api->setPayload(["geoserver" => get_object_vars($geoserver)]);
$api->Return();
