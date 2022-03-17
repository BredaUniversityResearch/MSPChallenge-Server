<?php
require_once '../init.php'; 

$api = new API;
$gamesave = new GameSave;
$user = new User();

$user->hastobeLoggedIn();

$gamesave->id = $_POST['save_id'] ?? "";
$gamesave->delete();

$api->setStatusSuccess();
$api->setPayload(["gamesave" => get_object_vars($gamesave)]);
$api->Return();

?>
