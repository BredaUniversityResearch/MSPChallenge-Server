<?php
require __DIR__ . '/../init.php';

$api = new API;
$servermanager = ServerManager::getInstance();

$servermanager->get();

$api->setPayload(["servermanager" =>
        ["server_name" => $servermanager->server_name,
        "server_description" => $servermanager->server_description]
]);
$api->setStatusSuccess();
$api->Return();
