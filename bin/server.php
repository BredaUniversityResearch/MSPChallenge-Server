<?php

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use App\Server;
use Ratchet\WebSocket\WsServer;

require dirname(__DIR__) . '/vendor/autoload.php';
$baseFolder = realpath(__DIR__ . '/../') . '/';
set_include_path(get_include_path() . PATH_SEPARATOR . $baseFolder);

$actualServer = new Server($baseFolder);
$server = IoServer::factory(new HttpServer(new WsServer($actualServer)), 8080);
$actualServer->registerLoop($server->loop);
$server->run();