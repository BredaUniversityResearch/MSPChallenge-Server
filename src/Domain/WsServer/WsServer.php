<?php
namespace App\Domain\WsServer;

use App\Domain\API\v1\Game;
use App\Domain\API\v1\Security;
use App\Domain\Event\NameAwareEvent;
use App\Domain\Helper\AsyncDatabase;
use App\Domain\Helper\Util;
use Closure;
use Exception;
use GuzzleHttp\Psr7\Request;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function App\assertFulfilled;
use function React\Promise\all;

class WsServer extends EventDispatcher implements MessageComponentInterface
{
    const HEADER_GAME_SESSION_ID = 'GameSessionId';
    const HEADER_MSP_API_TOKEN = 'MSPAPIToken';

    const EVENT_ON_CLIENT_CONNECTED = 'EVENT_ON_CLIENT_CONNECTED';
    const EVENT_ON_CLIENT_DISCONNNECTED = 'EVENT_ON_CLIENT_DISCONNNECTED';
    const EVENT_ON_CLIENT_ERROR = 'EVENT_ON_CLIENT_ERROR';
    const EVENT_ON_CLIENT_MESSAGE_RECEIVED = 'EVENT_ON_CLIENT_MESSAGE_RECEIVED';
    const EVENT_ON_CLIENT_MESSAGE_SENT = 'EVENT_ON_CLIENT_MESSAGE_SENT';
    const EVENT_ON_STATS_UPDATE = 'EVENT_ON_STATS_UPDATE';

    const TICK_MIN_INTERVAL_SEC = 2;
    const LATEST_MIN_INTERVAL_SEC = 0.2;

    private string $projectDir;
    private ?int $gameSessionId = null;
    private array $stats = [];
    private array $medianValues = [];

    private ?LoopInterface $loop = null;
    private array $clients = [];
    private array $clientInfoContainer = [];
    private array $clientHeaders = [];

    /**
     * @var int[]
     */
    private array $finishedTicksGameSessionIds = [];

    public function getStats(): array
    {
        return $this->stats;
    }

    public function __construct(
        string $projectDir,
        // below is required by legacy to be auto-wired
        \App\Domain\API\APIHelper $apiHelper
    ) {
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
        if (false === $this->getSecurity($headers[self::HEADER_GAME_SESSION_ID])->validateAccess(
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

        // clean up latest ticks by active game session ids.
        $clientInfoPerSessionContainer = collect($this->clientInfoContainer)
            ->groupBy(
                function ($value, $key) {
                    return $this->clientHeaders[$key][WsServer::HEADER_GAME_SESSION_ID];
                },
                true
            )
            ->all();
        $this->finishedTicksGameSessionIds = array_diff_key(
            $this->finishedTicksGameSessionIds,
            $clientInfoPerSessionContainer
        );

        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_DISCONNNECTED, $conn->resourceId));
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_ERROR, $conn->resourceId, [$e->getMessage()]));
        $conn->close();
    }

    private function statsLoopStart(string $category)
    {
        // reset
        $this->stats[$category.'.worst_of_prev_tick'] = 0;
    }

    private function statsLoopRegister(string $category, string $id, float $timeElapsed)
    {
        // init
        $this->stats[$category.'.worst_of_prev_tick'] ??= 0;
        $this->stats[$category.'.median'] ??= 0;

        $this->medianValues[$category][$id] = $timeElapsed;
        $this->stats[$category.'.worst_of_prev_tick'] = max(
            $this->stats[$category.'.worst_of_prev_tick'] ?? 0,
            $timeElapsed
        );
        $this->stats[$category.'.worst_ever'] = max($this->stats[$category.'.worst_ever'] ?? 0, $timeElapsed);
    }

    private function statsLoopEnd(string $category)
    {
        if (!array_key_exists($category.'.median', $this->stats)) {
            return;
        }
        $this->stats[$category.'.median'] = Util::getMedian($this->medianValues[$category]);
    }

    private function tick(): PromiseInterface
    {
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
        $timeStart = microtime(true);

        $promises = [];
        foreach ($clientInfoPerSessionContainer as $gameSessionId => $clientInfoContainer) {
            // for backwards compatibility
            $_REQUEST['session'] = $_GET['session'] = $gameSessionId;
            $_SERVER['REQUEST_URI'] = '';

            // stats BEGIN
            $tickTimeStart = microtime(true);
            $promises[$gameSessionId] = $this->getGame(
                $gameSessionId
            )->Tick()->then(
                function () use ($tickTimeStart, $gameSessionId) {
                    $this->statsLoopRegister('tick', $gameSessionId, microtime(true) - $tickTimeStart);
                    return $gameSessionId; // just to identify this tick
                }
            );
            // stats END
        }

        $timeElapsed = microtime(true) - $timeStart;
        $this->stats['loop'] = $timeElapsed;
        $this->stats['loop.worst_ever'] = max($this->stats['loop.worst_ever'] ?? 0, $timeElapsed);

        return all($promises);
    }

    private function latest(): PromiseInterface
    {
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
        $promises = [];
        $this->statsLoopStart('latest');
        foreach ($clientInfoPerSessionContainer as $gameSessionId => $clientInfoContainer) {
            // wait for a first finished tick
            if (!array_key_exists($gameSessionId, $this->finishedTicksGameSessionIds)) {
                continue;
            }

            // for backwards compatibility
            $_REQUEST['session'] = $_GET['session'] = $gameSessionId;
            $_SERVER['REQUEST_URI'] = '';
            foreach ($clientInfoContainer as $connResourceId => $clientInfo) {
                $accessTimeRemaining = 0; // not used
                if (false === $this->getSecurity($gameSessionId)->validateAccess(
                    Security::ACCESS_LEVEL_FLAG_FULL,
                    $accessTimeRemaining,
                    $this->clientHeaders[$connResourceId][self::HEADER_MSP_API_TOKEN]
                )) {
                    // Client's token has been expired, let the client re-connected with a new token.
                    $this->clients[$connResourceId]->close();
                    continue;
                }
                $latestTimeStart = microtime(true);
                $promises[$connResourceId] = $this->getGame($gameSessionId)->Latest(
                    $clientInfo['team_id'],
                    $clientInfo['last_update_time'],
                    $clientInfo['user']
                )
                ->then(function ($payload) use ($connResourceId, $latestTimeStart, $clientInfo) {
                    $this->statsLoopRegister('latest', $connResourceId, microtime(true) - $latestTimeStart);
                    if (empty($payload)) {
                        return [];
                    }
                    if (!array_key_exists($connResourceId, $this->clients)) {
                        // disconnected while running this async code, just return empty payload, nothing was sent...
                        $e = new ClientDisconnectedException();
                        $e->setConnResourceId($connResourceId);
                        throw $e;
                    }
                    // encountered another issue: mismatch between the "used" client info's last_update_time
                    //   and the "latest", so this payload will not be accepted, and should not be sent anymore...
                    if ($clientInfo['last_update_time'] !=
                        $this->clientInfoContainer[$connResourceId]['last_update_time']) {
                        // just return empty payload, nothing was sent...
                        return [];
                    }

//                    // do not allow any locked plans for this user
//                    if (collect($payload['plan'])
//                        ->where('locked', $clientInfo['user'])
//                        ->count()) {
//                        return [];
//                    }

                    // if the payload is equal to the previous one, no need to send it now
                    if (isset($this->clientInfoContainer[$connResourceId]['prev_payload'])) {
                        $p1 = $this->clientInfoContainer[$connResourceId]['prev_payload'];
                        $p2 = $payload;
                        unset(
                            $p1['prev_update_time'],
                            $p1['update_time'],
                            $p2['prev_update_time'],
                            $p2['update_time']
                        );
                        if (0 == strcmp(json_encode($p1), json_encode($p2))) {
                            unset($p1, $p2);
                            return []; // no need to send
                        }
                    }

                    $this->clientInfoContainer[$connResourceId]['prev_payload'] = $payload;
                    $this->clientInfoContainer[$connResourceId]['last_update_time'] = $payload['update_time'];
                    $json = json_encode([
                        "success" => true,
                        "message" => null,
                        "payload" => $payload
                    ]);
                    $this->clients[$connResourceId]->send($json);
                    return $payload;
                });
            }
        }
        return all($promises);
    }

    private function repeatedTickFunction(LoopInterface $loop): Closure
    {
        return function () use ($loop) {
            $startTime = microtime(true);
            assertFulfilled(
                $this->tick()
                    ->then(function (array $tickGameSessionIds) {
                        $this->finishedTicksGameSessionIds += $tickGameSessionIds;
                        $this->dispatch(
                            new NameAwareEvent(self::EVENT_ON_STATS_UPDATE)
                        );
                        $this->statsLoopEnd('tick');
                        // reset new loops after "stats update"
                        $this->statsLoopStart('tick');
                        $this->statsLoopStart('latest');
                    }),
                function () use ($loop, $startTime) {
                    $elapsedSec = (microtime(true) - $startTime) * 0.000001;
                    if ($elapsedSec > self::TICK_MIN_INTERVAL_SEC) {
                        $loop->futureTick($this->repeatedTickFunction($loop));
                        return;
                    }
                    $waitingSec = self::TICK_MIN_INTERVAL_SEC - $elapsedSec;
                    $loop->addTimer($waitingSec, function () use ($loop) {
                        $loop->futureTick($this->repeatedTickFunction($loop));
                    });
                }
            );
        };
    }

    private function repeatedLatestFunction(LoopInterface $loop): Closure
    {
        return function () use ($loop) {
            $startTime = microtime(true);
            assertFulfilled(
                $this->latest()
                    ->then(function (array $payloadContainer) {
                        $payloadContainer = array_filter($payloadContainer);
                        if (!empty($payloadContainer)) {
                            $this->dispatch(
                                new NameAwareEvent(
                                    self::EVENT_ON_CLIENT_MESSAGE_SENT,
                                    array_keys($payloadContainer),
                                    $payloadContainer
                                )
                            );
                        }
                        $this->statsLoopEnd('latest');
                    })
                    ->otherwise(function (ClientDisconnectedException $e) {
                        // nothing to do.
                    }),
                function () use ($loop, $startTime) {
                    $elapsedSec = (microtime(true) - $startTime) * 0.000001;
                    if ($elapsedSec > self::LATEST_MIN_INTERVAL_SEC) {
                        $loop->futureTick($this->repeatedLatestFunction($loop));
                        return;
                    }
                    $waitingSec = self::LATEST_MIN_INTERVAL_SEC - $elapsedSec;
                    $loop->addTimer($waitingSec, function () use ($loop) {
                        $loop->futureTick($this->repeatedLatestFunction($loop));
                    });
                }
            );
        };
    }

    public function registerLoop(LoopInterface $loop)
    {
        $this->loop = $loop;
        $loop->futureTick($this->repeatedTickFunction($loop));
        $loop->futureTick($this->repeatedLatestFunction($loop));
    }

    /**
     * @throws Exception
     */
    private function getGame(int $gameSessionId): Game
    {
        static $instances = [];
        if (!array_key_exists($gameSessionId, $instances)) {
            $game = new Game();
            $game->setAsyncDatabase(
                AsyncDatabase::createGameSessionConnection($this->loop, $gameSessionId),
            );

            // do some PRE CACHING calls
            $game->GetWatchdogAddress(true);
            $game->LoadConfigFile();

            $instances[$gameSessionId] = $game;
        }
        return $instances[$gameSessionId];
    }

    private function getSecurity(int $gameSessionId): Security
    {
        static $instances = [];
        if (!array_key_exists($gameSessionId, $instances)) {
            $instances[$gameSessionId] = new Security();
        }
        return $instances[$gameSessionId];
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        if ($event instanceof NameAwareEvent) {
            return parent::dispatch($event, $event->getEventName());
        }
        return parent::dispatch($event, $eventName);
    }
}
