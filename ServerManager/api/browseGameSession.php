<?php

use ServerManager\API;
use ServerManager\GameSession;
use ServerManager\ServerManager;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$gamesession = new GameSession;
$user = new User();
$servermanager = ServerManager::getInstance();
$servermanager->get();

// security disabled because the client uses this endpoint too - $user->hastobeLoggedIn();

$session_state = $_POST['session_state'] ?? 'public';
$where_array_session_state = ($session_state == 'public') ?
    array("games.session_state", "!=", "archived") : array("games.session_state", "=", $session_state);

$demo_session = $_POST['demo_servers'] ?? 0;
if (empty($demo_session)) {
    $where_array = $where_array_session_state;
} else {
    $where_array_demo_session = array("games.demo_session", "=", $demo_session);
    $where_array = array("AND", $where_array_session_state, $where_array_demo_session);
}

$api->setPayload([
    "sessionslist" => $gamesession->getList($where_array),
    "server_description" => $servermanager->serverDescription
]);
$api->setStatusSuccess();
$api->Return();
