<?php
require_once '../init.php'; 

$user->hastobeLoggedIn();

$api = new API;
$gamesession = new GameSession;

$provider = $_POST["provider"] ?? "";
$users = $_POST["users"] ?? "";
$session_id = $_POST["session_id"] ?? "";

$get = $gamesession->get($session_id);

if ($get !== true) {
    $api->setStatusFailure();
	$api->setMessage($get);
}
else {
    $server_call = $gamesession->callServer(
        "user/checkExists",
        array("provider" => $provider, "users" => $users),
        $session_id,
        $gamesession->api_access_token
    );

    if ($server_call["success"]) {
        $api->setStatusSuccess();
        $api->setPayload(["found" => $server_call["payload"]["found"]]);
        $api->setPayload(["notfound" => $server_call["payload"]["notfound"]]);
    } else
    {
        $api->setStatusFailure();
        $api->setMessage($server_call["message"]);
    }
}

$api->printReturn();
?>
