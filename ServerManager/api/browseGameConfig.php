<?php
require_once '../init.php'; 

$api = new API;
$gameconfig = new GameConfig;
$user = new User();

$user->hastobeLoggedIn();

$visibility = $_POST['visibility'] ?? 'active';
$where_array = array("visibility", "=", $visibility);

$api->setPayload(["configslist" => $gameconfig->getList($where_array)]);
$api->setStatusSuccess();
$api->Return();

?>
