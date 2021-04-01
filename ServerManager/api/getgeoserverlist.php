<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$api = new API;
$geoserver = new GeoServer;

$geoservers = $geoserver->getList();

if (is_array($geoservers)) {
	$api->setPayload($geoservers);
	$api->setStatusSuccess();
}
else {
	$api->setMessage($geoservers);
}

$api->printReturn();
?>
