<?php
require_once '../init.php'; 

$api = new API;
$gamesave = new GameSave;
$user = new User();

$user->hastobeLoggedIn();

// first check if the save_id referred to can even be obtained
$gamesave->id = $_POST["save_id"] ?? "";
$gamesave->get();

// now optionally change all the object vars
$gamesave->processPostedVars();

// then perform any allowed and existing object action requested
$allowed_actions = array(
    "processZip" // called by server API gamesession/CreateGameSessionZip and gamesession/CreateGameSessionLayersZip
);
$action = $_POST["action"] ?? "";
if (method_exists($gamesave, $action) && in_array($action, $allowed_actions)) 
{
    $api->setPayLoad([$action => $gamesave->$action()]);
}

// ready to do final actual update
$gamesave->edit();
$api->setPayLoad(["gamesave" => get_object_vars($gamesave)]);
$api->setStatusSuccess();
$api->Return();

?>