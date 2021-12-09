<?php
namespace App;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;

class Server implements MessageComponentInterface {
    private string $baseFolder;

    protected $clients;
    protected $clientInfoContainer;

    public function __construct(string $baseFolder)
    {
        $this->baseFolder = $baseFolder;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients[$conn->resourceId] = $conn;
        echo sprintf('Connection added: %s' . PHP_EOL, $conn->resourceId);
        echo sprintf('#Connections: %d' . PHP_EOL, count($this->clients));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $this->clientInfoContainer[$from->resourceId] = json_decode($msg, true);
        echo sprintf('client %s data received:' . PHP_EOL . '%s' . PHP_EOL, $from->resourceId, $msg);
    }

    public function onClose(ConnectionInterface $conn) {
        unset($this->clients[$conn->resourceId]);
        unset($this->clientInfoContainer[$conn->resourceId]);
        echo sprintf('Connection removed: %s' . PHP_EOL, $conn->resourceId);
        echo sprintf('#Connections: %d' . PHP_EOL, count($this->clients));
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo sprintf('client %s error: ' . PHP_EOL . '%s' . PHP_EOL, $conn->resourceId, $e->getMessage());
        $conn->close();
    }

    public function registerLoop(LoopInterface $loop)
    {
        $loop->addPeriodicTimer(4, function() {
            require_once('api/class.apihelper.php');
            \APIHelper::SetupApiLoader($this->baseFolder);
            $game = new \Game();

            // todo: change all database connection to use async drift dbal connector
            $clientInfoPerSessionContainer = collect($this->clientInfoContainer)->groupBy('game_session_id', true);
            foreach ($clientInfoPerSessionContainer as $gameSessionId => $clientInfoContainer) {
                $_GET['session'] = $gameSessionId;
                $_SERVER['REQUEST_URI'] = '';
                $game->Tick();
                foreach ($clientInfoContainer as $connResourceId => $clientInfo) {
                    $payload = $game->Latest($clientInfo['team_id'], $clientInfo['last_update_time'], $clientInfo['user']);
                    if (empty($payload)) {
                        continue;
                    }
                    $this->clientInfoContainer[$connResourceId]['last_update_time'] = $payload['update_time'];
                    $json = json_encode([
                        "success" => true,
                        "message" => null,
                        "payload" => $payload
                    ]);
                    echo sprintf('client %s data sent: ' . PHP_EOL . '%s' . PHP_EOL, $connResourceId, $json);
                    $this->clients[$connResourceId]->send($json);
                }
            }
        });
    }
}