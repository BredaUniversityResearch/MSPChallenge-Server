<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$api = new API;

$gamesession = new GameSession;
$id = $_POST["session_id"] ?? "";
$get = $gamesession->get($id);

if ($get !== true)
{
	$api->setStatusFailure();
	$api->setMessage($get);
} else 
{
	$api->setStatusSuccess();
	$api->setPayload(["gamesession" => get_object_vars($gamesession)]);
    $api->setPayload(["countries" => $gamesession->getCountries()]);
}

$api->printReturn();

?>
