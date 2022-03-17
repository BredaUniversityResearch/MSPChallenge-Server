<?php
require_once '../init.php'; 

$api = new API;
$gameconfig = new GameConfig;
$user = new User();

$user->hastobeLoggedIn();

$gameconfig->id = $_POST['config_version_id'] ?? "";
$gameconfig->delete();

$api->setStatusSuccess();
$api->setPayload(["gameconfig" => get_object_vars($gameconfig)]);
$api->Return();

?>
