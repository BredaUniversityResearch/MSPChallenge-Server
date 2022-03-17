<?php

require_once '../init.php'; 

$api = new API;
$gamesave = new GameSave;
$gamesession = new GameSession;
$user = new User();

$user->hastobeLoggedIn();

// first check if the save_id referred to can even be obtained
$gamesave->id = $_POST["save_id"] ?? "";
$gamesave->get();

//  then perform any allowed and existing object action requested
$allowed_actions = array(
    "load", // called in JS function submitLoadSave
);
$action = $_POST["action"] ?? "";
if (method_exists($gamesave, $action) && in_array($action, $allowed_actions)) 
{
    $api->setPayload([$action => $gamesave->$action()]);
}

// now see which associated GameSessions can be obtained
$gamesessions = $gamesession->getList(array("save_id", "=", $gamesave->id));

// ok, return everything
$api->setStatusSuccess();
$api->setPayload(["gamesave" => get_object_vars($gamesave)]);
$api->setPayload(["gamesave_pretty" => $gamesave->getPrettyVars()]);
$api->setPayload(["gamesessions" => $gamesessions]);
$api->Return();

?>