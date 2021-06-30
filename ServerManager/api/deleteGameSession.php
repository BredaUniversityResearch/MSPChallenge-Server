<?php
require_once '../init.php'; 

$api = new API;
$gamesession = new GameSession;
$user = new User();

$user->hastobeLoggedIn();

$gamesession->id = $_POST['session_id'] ?? "";
$gamesession->delete();

$api->setStatusSuccess();
$api->setPayload(["gamesession" => get_object_vars($gamesession)]);
$api->Return();

?>
