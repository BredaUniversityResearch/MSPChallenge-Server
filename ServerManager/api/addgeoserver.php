<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$api = new API;

$geoserver = new GeoServer;
$geoserver->name = $_POST['name'] ?? "";
$geoserver->address = $_POST['address'] ?? "";
$geoserver->username = $_POST['username'] ?? "";
$geoserver->password = $_POST['password'] ?? "";

$created = $geoserver->create();
if ($created !== true)
{
	$api->setStatusFailure();
	$api->setMessage($created);
} else 
{
	$api->setStatusSuccess();
	$api->setMessage("GeoServer has been added.");
}

$api->printReturn();

?>
