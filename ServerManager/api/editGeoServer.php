<?php

use ServerManager\API;
use ServerManager\GeoServer;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$geoserver = new GeoServer;
$user = new User();

$user->hasToBeLoggedIn();

// first check if the geoserver_id referred to can even be obtained
$geoserver->id = $_POST["geoserver_id"] ?? "";
$geoserver->get();

// now optionally change all the object vars - not using processPostedVars() here because of exception below
$geoserver->name = $_POST['name'] ?? $geoserver->name;
$geoserver->address = $_POST['address'] ?? $geoserver->address;
// only exception to the rule of having the class encode it, this is because we never decode it again in the
//   ServerManager
$geoserver->username = isset($_POST['username']) ? base64_encode($_POST['username']) : $geoserver->username;
$geoserver->password = isset($_POST['password']) ? base64_encode($_POST['password']) : $geoserver->password;

// ready to do final actual update
$geoserver->edit();
$api->setPayLoad(["geoserver" => get_object_vars($geoserver)]);
$api->setStatusSuccess();
$api->Return();
