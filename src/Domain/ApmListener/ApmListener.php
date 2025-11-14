<?php

namespace App\Domain\ApmListener;

use App\Domain\WsServer\WsServerConnection;
use Ratchet\Http\HttpServer;
use React\EventLoop\LoopInterface;
use React\Socket\TcpServer;
use React\Socket\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface as WsConnectionInterface;

/***
 * @description This class is a WebSocket server that has 2 jobs:
 * - listens for incoming events, from APM, the Simulation, the server itself through a TCP socket server
 * - broadcasts them to connected clients through WebSocket connections
 */
class ApmListener implements MessageComponentInterface
{
    /**
     * @var WsServerConnection[]
     */
    private array $clients = [];

    public function __construct(
        private readonly int $tcpPort = 45100,
        private readonly int $wsPort = 45101,
        private readonly string $tcpAddress = '127.0.0.1',
        private readonly string $wsAddress = '0.0.0.0',
    ) {
    }

    // WebSocket interface methods
    public function onOpen(WsConnectionInterface $conn): void
    {
        $conn = new WsServerConnection($conn);
        echo sprintf("New connection: %d\n", $conn->resourceId);
        $this->clients[$conn->resourceId] = $conn;
        $conn->send('Welcome to the APM listener!');
    }

    public function onMessage(WsConnectionInterface $from, $msg): void
    {
        $from = new WsServerConnection($from);
        echo sprintf("Received $msg from %d\n", $from->resourceId);
        // Optionally handle incoming messages from WebSocket clients
    }

    public function onClose(WsConnectionInterface $conn): void
    {
        $conn = new WsServerConnection($conn);
        echo sprintf("Connection %d closed\n", $conn->resourceId);
        unset($this->clients[$conn->resourceId]);
    }

    public function onError(WsConnectionInterface $conn, \Exception $e): void
    {
        $conn = new WsServerConnection($conn);
        echo sprintf("Error: %s for %d\n", $e->getMessage(), $conn->resourceId);
        $conn->close();
    }

    public function run(): void
    {
        // Create the WebSocket server using IoServer::factory
        $server = IoServer::factory(
            new HttpServer(new WsServer($this)),
            $this->wsPort,
            $this->wsAddress
        );

        // Create the TCP server for incoming APM events, using the same loop
        $this->createTcpServer($server->loop);

        $this->registerSignalListeners($server->loop);

        // Start the server (runs the event loop)
        $server->run();
    }

    private function broadcast($message): void
    {
        foreach ($this->clients as $client) {
            echo sprintf("Broadcasting: $message to %d\n", $client->resourceId);
            $client->send($message);
        }
    }

    public function registerSignalListeners(LoopInterface $loop): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () use ($loop) {
                $loop->stop();
            });
            pcntl_signal(SIGINT, function () use ($loop) {
                $loop->stop();
            });
            $loop->addPeriodicTimer(1, function () {
                pcntl_signal_dispatch();
            });
        }
        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function ($event) use ($loop) {
                if ($event === PHP_WINDOWS_EVENT_CTRL_C) {
                    $loop->stop();
                }
            });
        }
    }

    /**
     * @description Create a TCP socket server for handling incoming APM events, which are sent as JSON objects.
     */
    public function createTcpServer(LoopInterface $loop): void
    {
        $tcpServer = new TcpServer($this->tcpAddress . ':' . $this->tcpPort, $loop);
        $tcpServer->on('connection', function (ConnectionInterface $conn) {
            $buffer = '';
            $braceCount = 0;
            $jsonStart = null;
            $conn->on('data', function ($data) use (&$buffer, &$braceCount, &$jsonStart) {
                echo "Received: $data\n";
                $buffer .= $data;
                $len = strlen($buffer);
                for ($i = 0; $i < $len; $i++) {
                    if ($buffer[$i] === '{') {
                        if ($braceCount === 0) {
                            $jsonStart = $i;
                        }
                        $braceCount++;
                    } elseif ($buffer[$i] === '}') {
                        $braceCount--;
                        if ($braceCount === 0 && $jsonStart !== null) {
                            // Ah finally, we have a complete JSON object!
                            $jsonString = substr($buffer, $jsonStart, $i - $jsonStart + 1);
                            // Broadcast the JSON string to all connected WebSocket clients
                            $this->broadcast($jsonString);
                            // Remove processed JSON from buffer
                            $buffer = substr($buffer, $i + 1);
                            $len = strlen($buffer);
                            $i = -1; // reset loop to start of new buffer
                            $jsonStart = null;
                        }
                    }
                }
            });
        });
    }
}
