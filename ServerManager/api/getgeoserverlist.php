<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$api = new API;
$geoserver = new GeoServer;

$geoservers = $geoserver->getList();

if (is_array($geoservers)) {
	$api->setStatusSuccess();
	$api->setPayload($geoservers);
}
else {
	$api->setStatusFailure();
	$api->setMessage($geoservers);
}

$api->printReturn();
?>
