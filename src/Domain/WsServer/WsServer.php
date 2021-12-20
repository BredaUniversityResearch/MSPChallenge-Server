<?php
namespace App\Domain\WsServer;

use APIHelper;
use App\Domain\Event\NameAwareEvent;
use App\Domain\Helper\Util;
use Exception;
use Game;
use GuzzleHttp\Psr7\Request;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use Security;
use Symfony\Component\EventDispatcher\EventDispatcher;

class WsServer extends EventDispatcher implements MessageComponentInterface
{
    const HEADER_GAME_SESSION_ID = 'GameSessionId';
    const HEADER_MSP_API_TOKEN = 'MSPAPIToken';

    const EVENT_ON_CLIENT_CONNECTED = 'EVENT_ON_CLIENT_CONNECTED';
    const EVENT_ON_CLIENT_DISCONNNECTED = 'EVENT_ON_CLIENT_DISCONNNECTED';
    const EVENT_ON_CLIENT_ERROR = 'EVENT_ON_CLIENT_ERROR';
    const EVENT_ON_CLIENT_MESSAGE_RECEIVED = 'EVENT_ON_CLIENT_MESSAGE_RECEIVED';
    const EVENT_ON_CLIENT_MESSAGE_SENT = 'EVENT_ON_CLIENT_MESSAGE_SENT';

    private string $projectDir;
    private ?int $gameSessionId = null;
    private array $stats = [];
    private array $medianValues = [];

    protected array $clients = [];
    protected array $clientInfoContainer = [];
    protected array $clientHeaders = [];

    public function getStats(): array
    {
        return $this->stats;
    }

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        parent::__construct();
    }

    public function setGameSessionId(int $gameSessionId): void
    {
        $this->gameSessionId = $gameSessionId;
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
        $httpRequest = $conn->httpRequest;
        /** @var Request $httpRequest */
        $headers = collect($httpRequest->getHeaders())
            ->map(function (array $value) {
                return $value[0];
            })
            ->all();

        // required headers are not there, do not allow connection
        if (!array_key_exists(self::HEADER_GAME_SESSION_ID, $headers) ||
            !array_key_exists(self::HEADER_MSP_API_TOKEN, $headers)) {
            $conn->close();
            return;
        }
        if (null != $this->gameSessionId && $this->gameSessionId != $headers[self::HEADER_GAME_SESSION_ID]) {
            // do not connect this client, client is from another game session.
            $conn->close();
            return;
        }


        // not a valid token, connection not allowed
        $accessTimeRemaining = 0;
        $_REQUEST['session'] = $_GET['session'] = $headers[self::HEADER_GAME_SESSION_ID];
        if (false === $this->getSecurity()->validateAccess(
            Security::ACCESS_LEVEL_FLAG_FULL,
            $accessTimeRemaining,
            $headers[self::HEADER_MSP_API_TOKEN]
        )) {
            $conn->close();
            return;
        }

        $this->clients[$conn->resourceId] = $conn;
        $this->clientHeaders[$conn->resourceId] = $headers;
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_CONNECTED, $conn->resourceId, $headers));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $clientInfo = json_decode($msg, true);
        $this->clientInfoContainer[$from->resourceId] = $clientInfo;
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_MESSAGE_RECEIVED, $from->resourceId, $clientInfo));
    }

    public function onClose(ConnectionInterface $conn)
    {
        unset($this->clients[$conn->resourceId]);
        unset($this->clientInfoContainer[$conn->resourceId]);
        unset($this->clientHeaders[$conn->resourceId]);
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_DISCONNNECTED, $conn->resourceId));
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_ERROR, $conn->resourceId, [$e->getMessage()]));
        $conn->close();
    }

    private function statsLoopStart(string $category)
    {
        $this->medianValues[$category] = [];
        $this->stats[$category.'.worst_of_loop'] = 0;
        $this->stats[$category.'.median_of_loop'] = 0;
    }

    private function statsLoopRegister(string $category, float $timeElapsed)
    {
        $this->medianValues[$category][] = $timeElapsed;
        $this->stats[$category.'.worst_of_loop'] = max(
            $this->stats[$category.'.worst_of_loop'] ?? 0,
            $timeElapsed
        );
        $this->stats[$category.'.worst_ever'] = max($this->stats[$category.'.worst_ever'] ?? 0, $timeElapsed);
    }

    private function statsLoopEnd(string $category)
    {
        $this->stats[$category.'.median_of_loop'] = Util::getMedian($this->medianValues[$category]);
    }

    public function registerLoop(LoopInterface $loop)
    {
        set_include_path(get_include_path() . PATH_SEPARATOR . $this->projectDir);
        require_once 'api/class.apihelper.php';
        APIHelper::SetupApiLoader($this->projectDir . '/');

        $loop->addPeriodicTimer(4, function () {
            // todo: change all database connection to using drift/dbal
            $clientInfoPerSessionContainer = collect($this->clientInfoContainer)
                ->groupBy(
                    function ($value, $key) {
                        return $this->clientHeaders[$key][WsServer::HEADER_GAME_SESSION_ID];
                    },
                    true
                );
            if ($this->gameSessionId != null) {
                $clientInfoPerSessionContainer = $clientInfoPerSessionContainer->only($this->gameSessionId);
            }
            $dataSent = [];
            $timeStart = microtime(true);

            $this->statsLoopStart('tick');
            $this->statsLoopStart('latest');
            foreach ($clientInfoPerSessionContainer as $gameSessionId => $clientInfoContainer) {
                // for backwards compatibility
                $_REQUEST['session'] = $_GET['session'] = $gameSessionId;
                $_SERVER['REQUEST_URI'] = '';

                // stats BEGIN
                $tickTimeStart = microtime(true);
                $this->getGame()->Tick();
                $this->statsLoopRegister('tick', microtime(true) - $tickTimeStart);
                // stats END

                foreach ($clientInfoContainer as $connResourceId => $clientInfo) {
                    $latestTimeStart = microtime(true);
                    $accessTimeRemaining = 0; // not used
                    if (false === $this->getSecurity()->validateAccess(
                        Security::ACCESS_LEVEL_FLAG_FULL,
                        $accessTimeRemaining,
                        $this->clientHeaders[$connResourceId][self::HEADER_MSP_API_TOKEN]
                    )) {
                        // Client's token has been expired, let the client re-connected with a new token.
                        $this->clients[$connResourceId]->close();

                        $this->statsLoopRegister('latest', microtime(true) - $latestTimeStart);
                        continue;
                    }
                    $payload = $this->getGame()->Latest(
                        $clientInfo['team_id'],
                        $clientInfo['last_update_time'],
                        $clientInfo['user']
                    );
                    if (empty($payload)) {
                        $this->statsLoopRegister('latest', microtime(true) - $latestTimeStart);
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

                    $this->statsLoopRegister('latest', microtime(true) - $latestTimeStart);
                }
            }
            $this->statsLoopEnd('tick');
            $this->statsLoopEnd('latest');

            $timeElapsed = microtime(true) - $timeStart;
            $this->stats['loop'] = $timeElapsed;
            $this->stats['loop.worst_ever'] = max($this->stats['loop.worst_ever'] ?? 0, $timeElapsed);
            if (!empty($dataSent)) {
                $this->dispatch(
                    new NameAwareEvent(self::EVENT_ON_CLIENT_MESSAGE_SENT, array_keys($dataSent), $dataSent)
                );
            }
        });
    }

    private function getGame(): Game
    {
        static $game = null;
        if (null == $game) {
            $game = new Game();
        }
        return $game;
    }

    private function getSecurity(): Security
    {
        static $security = null;
        if (null == $security) {
            $security = new Security();
        }
        return $security;
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        if ($event instanceof NameAwareEvent) {
            return parent::dispatch($event, $event->getEventName());
        }
        return parent::dispatch($event, $eventName);
    }
}
