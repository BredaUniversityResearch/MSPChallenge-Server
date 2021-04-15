<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$api = new API;

$base = new Base;

$server_call = $base->callServer(
    "user/getProviders"
);

if ($server_call["success"]) {
    $api->setStatusSuccess();
    $api->setPayload(["providers" => $server_call["payload"]]);
} else
{
    $api->setStatusFailure();
    $api->setMessage($server_call["message"]);
}

$api->printReturn();
?>
