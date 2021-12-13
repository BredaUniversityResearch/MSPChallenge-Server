<?php
namespace App\Domain\WsServer;

use APIHelper;
use App\Domain\Event\NameAwareEvent;
use Exception;
use Game;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class WsServer extends EventDispatcher implements MessageComponentInterface
{
    const EVENT_ON_CLIENT_CONNECTED = 'EVENT_ON_CLIENT_CONNECTED';
    const EVENT_ON_CLIENT_DISCONNNECTED = 'EVENT_ON_CLIENT_DISCONNNECTED';
    const EVENT_ON_CLIENT_ERROR = 'EVENT_ON_CLIENT_ERROR';
    const EVENT_ON_CLIENT_MESSAGE_RECEIVED = 'EVENT_ON_CLIENT_MESSAGE_RECEIVED';
    const EVENT_ON_CLIENT_MESSAGE_SENT = 'EVENT_ON_CLIENT_MESSAGE_SENT';

    private string $projectDir;

    protected array $clients = [];
    protected array $clientInfoContainer = [];

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        parent::__construct();
    }

    public function getClientInfo(int $clientResourceId): ?array
    {
        if (!array_key_exists($clientResourceId, $this->clientInfoContainer)) {
            return null;
        }
        return $this->clientInfoContainer[$clientResourceId];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients[$conn->resourceId] = $conn;
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_CONNECTED, $conn->resourceId));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        $this->clientInfoContainer[$from->resourceId] = $data;
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_MESSAGE_RECEIVED, $from->resourceId, $data));
    }

    public function onClose(ConnectionInterface $conn)
    {
        unset($this->clients[$conn->resourceId]);
        unset($this->clientInfoContainer[$conn->resourceId]);
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_DISCONNNECTED, $conn->resourceId));
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_ERROR, $conn->resourceId, [$e->getMessage()]));
        $conn->close();
    }

    public function registerLoop(LoopInterface $loop)
    {
        set_include_path(get_include_path() . PATH_SEPARATOR . $this->projectDir);

        $loop->addPeriodicTimer(4, function () {
            require_once('api/class.apihelper.php');
            APIHelper::SetupApiLoader($this->projectDir . '/');
            $game = new Game();

            // todo: change all database connection to use async drift dbal connector
            $clientInfoPerSessionContainer = collect($this->clientInfoContainer)->groupBy('game_session_id', true);
            $dataSent = [];
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
                    $dataSent[$connResourceId] = $payload;
                    $this->clients[$connResourceId]->send($json);
                }
            }
            if (!empty($dataSent)) {
                $this->dispatch(
                    new NameAwareEvent(self::EVENT_ON_CLIENT_MESSAGE_SENT, array_keys($dataSent), $dataSent)
                );
            }
        });
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        if ($event instanceof NameAwareEvent) {
            return parent::dispatch($event, $event->getEventName());
        }
        return parent::dispatch($event, $eventName);
    }
}
