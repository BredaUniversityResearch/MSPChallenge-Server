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
use Illuminate\Support\Collection;
use PDOException;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function App\assertFulfilled;
use function React\Promise\all;

function wdo(string $message) // WsServer debugging output if enabled by WS_SERVER_DEBUG_OUTPUT via .env file
{
    if (empty($_ENV['WS_SERVER_DEBUG_OUTPUT'])) {
        return;
    }
    echo $message . PHP_EOL;
}

class WsServer extends EventDispatcher implements MessageComponentInterface
{
    const HEADER_GAME_SESSION_ID = 'GameSessionId';
    const HEADER_MSP_API_TOKEN = 'MSPAPIToken';
    const LATEST_CLIENT_UPDATE_SPEED = 1.0;

    const EVENT_ON_CLIENT_CONNECTED = 'EVENT_ON_CLIENT_CONNECTED';
    const EVENT_ON_CLIENT_DISCONNNECTED = 'EVENT_ON_CLIENT_DISCONNNECTED';
    const EVENT_ON_CLIENT_ERROR = 'EVENT_ON_CLIENT_ERROR';
    const EVENT_ON_CLIENT_MESSAGE_RECEIVED = 'EVENT_ON_CLIENT_MESSAGE_RECEIVED';
    const EVENT_ON_CLIENT_MESSAGE_SENT = 'EVENT_ON_CLIENT_MESSAGE_SENT';
    const EVENT_ON_STATS_UPDATE = 'EVENT_ON_STATS_UPDATE';

    const TICK_MIN_INTERVAL_SEC = 2;
    const LATEST_MIN_INTERVAL_SEC = 0.2;

    private ?int $gameSessionId = null;
    private array $stats = [];
    private array $medianValues = [];

    private ?LoopInterface $loop = null;
    private array $clients = [];
    private array $clientInfoContainer = [];
    private array $clientHeaders = [];

    /**
     * @var Game[]
     */
    private array $gameInstances = [];

    /**
     * @var Security[]
     */
    private array $securityInstances = [];

    /**
     * @var int[]
     */
    private array $finishedTicksGameSessionIds = [];

    public function getStats(): array
    {
        return $this->stats;
    }

    public function __construct(
        // below is required by legacy to be auto-wired
        \App\Domain\API\APIHelper $apiHelper
    ) {
        // for backwards compatibility, to prevent missing request data errors
        $_SERVER['REQUEST_URI'] = '';

        parent::__construct();
    }

    public function setGameSessionId(int $gameSessionId): void
    {
        $this->gameSessionId = $gameSessionId;
    }

    public function getClientHeaders(int $clientResourceId): ?array
    {
        if (!array_key_exists($clientResourceId, $this->clientHeaders)) {
            return null;
        }
        return $this->clientHeaders[$clientResourceId];
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

        if (!array_key_exists(self::HEADER_GAME_SESSION_ID, $headers) ||
            !array_key_exists(self::HEADER_MSP_API_TOKEN, $headers)) {
            // required headers are not there, do not allow connection
            wdo('required headers are not there, do not allow connection');
            $conn->close();
            return;
        }
        if (null != $this->gameSessionId && $this->gameSessionId != $headers[self::HEADER_GAME_SESSION_ID]) {
            // do not connect this client, client is from another game session
            wdo('do not connect this client, client is from another game session');
            $conn->close();
            return;
        }

        $accessTimeRemaining = 0;
        if (false === $this->getSecurity($headers[self::HEADER_GAME_SESSION_ID])->validateAccess(
            Security::ACCESS_LEVEL_FLAG_FULL,
            $accessTimeRemaining,
            $headers[self::HEADER_MSP_API_TOKEN]
        )) {
            // not a valid token, connection not allowed
            wdo('not a valid token, connection not allowed');
            $conn->close();
            return;
        }

        $this->clients[$conn->resourceId] = $conn;
        $this->clientHeaders[$conn->resourceId] = $headers;
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_CONNECTED, $conn->resourceId, $headers));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        if (!array_key_exists($from->resourceId, $this->clientHeaders)) {
            wdo('received message, although no connection headers were registered... ignoring...');
            return;
        }

        $clientInfo = json_decode($msg, true);
        $this->clientInfoContainer[$from->resourceId] = $clientInfo;
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_MESSAGE_RECEIVED, $from->resourceId, $clientInfo));
    }

    public function onClose(ConnectionInterface $conn)
    {
        unset($this->clients[$conn->resourceId]);
        unset($this->clientInfoContainer[$conn->resourceId]);
        unset($this->clientHeaders[$conn->resourceId]);

        // clean up latest ticks and instances by active game session ids.
        $clientInfoPerSessionContainer = $this->getClientInfoPerSessionCollection()->all();
        $this->finishedTicksGameSessionIds = array_diff_key(
            $this->finishedTicksGameSessionIds,
            $clientInfoPerSessionContainer
        );
        $this->gameInstances = array_diff_key(
            $this->gameInstances,
            $clientInfoPerSessionContainer
        );
        $this->securityInstances = array_diff_key(
            $this->securityInstances,
            $clientInfoPerSessionContainer
        );

        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_DISCONNNECTED, $conn->resourceId));
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        // detect PDOException, could also be a previous exception
        while (true) {
            if ($e instanceof PDOException) {
                throw $e; // let the Websocket server crash on database query errors.
            }
            if (null === $previous = $e->getPrevious()) {
                break;
            }
            $e = $previous;
        }
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
        wdo('starting "tick"');
        $clientInfoPerSessionCollection = $this->getClientInfoPerSessionCollection();
        if ($this->gameSessionId != null) {
            $clientInfoPerSessionCollection = $clientInfoPerSessionCollection->only($this->gameSessionId);
        }
        $timeStart = microtime(true);

        $promises = [];
        foreach ($clientInfoPerSessionCollection as $gameSessionId => $clientInfoContainer) {
            wdo('starting "tick" for game session: ' . $gameSessionId);
            $tickTimeStart = microtime(true);
            $promises[$gameSessionId] = $this->getGame($gameSessionId)->Tick(!empty($_ENV['WS_SERVER_DEBUG_OUTPUT']))
                ->then(
                    function () use ($tickTimeStart, $gameSessionId) {
                        $this->statsLoopRegister('tick', $gameSessionId, microtime(true) - $tickTimeStart);
                        return $gameSessionId; // just to identify this tick
                    }
                );
        }

        $timeElapsed = microtime(true) - $timeStart;
        $this->stats['loop'] = $timeElapsed;
        $this->stats['loop.worst_ever'] = max($this->stats['loop.worst_ever'] ?? 0, $timeElapsed);

        return all($promises);
    }

    private function latest(): PromiseInterface
    {
        wdo('starting "latest"');
        $clientInfoPerSessionContainer = collect($this->clientInfoContainer)
            ->groupBy(
                function ($value, $key) {
                    return $this->clientHeaders[$key][self::HEADER_GAME_SESSION_ID];
                },
                true
            );
        if ($this->gameSessionId != null) {
            $clientInfoPerSessionContainer = $clientInfoPerSessionContainer->only($this->gameSessionId);
        }
        $promises = [];
        $this->statsLoopStart('latest');
        foreach ($clientInfoPerSessionContainer as $gameSessionId => $clientInfoContainer) {
            if (!array_key_exists($gameSessionId, $this->finishedTicksGameSessionIds)) {
                // wait for a first finished tick
                wdo('wait for a first finished tick: ' . $gameSessionId);
                continue;
            }

            foreach ($clientInfoContainer as $connResourceId => $clientInfo) {
                $accessTimeRemaining = 0; // not used
                if (false === $this->getSecurity($gameSessionId)->validateAccess(
                    Security::ACCESS_LEVEL_FLAG_FULL,
                    $accessTimeRemaining,
                    $this->clientHeaders[$connResourceId][self::HEADER_MSP_API_TOKEN]
                )) {
                    // Client's token has been expired, let the client re-connected with a new token
                    wdo('Client\'s token has been expired, let the client re-connected with a new token');
                    $this->clients[$connResourceId]->close();
                    continue;
                }
                $latestTimeStart = microtime(true);
                wdo('Starting "latest" for: ' . $connResourceId);
                $promises[$connResourceId] = $this->getGame($gameSessionId)->Latest(
                    $clientInfo['team_id'],
                    $clientInfo['last_update_time'],
                    $clientInfo['user']
                )
                ->then(function ($payload) use ($connResourceId, $latestTimeStart, $clientInfo) {
                    wdo('Created "latest" payload for: ' . $connResourceId);
                    $this->statsLoopRegister('latest', $connResourceId, microtime(true) - $latestTimeStart);
                    if (empty($payload)) {
                        wdo('empty payload');
                        return [];
                    }
                    if (!array_key_exists($connResourceId, $this->clients)) {
                        // disconnected while running this async code, just return empty payload, nothing was sent
                        wdo('disconnected while running this async code, just return empty payload, nothing was sent');
                        $e = new ClientDisconnectedException();
                        $e->setConnResourceId($connResourceId);
                        throw $e;
                    }
                    if ($clientInfo['last_update_time'] !=
                        $this->clientInfoContainer[$connResourceId]['last_update_time']) {
                        // encountered another issue: mismatch between the "used" client info's last_update_time
                        //   and the "latest", so this payload will not be accepted, and should not be sent anymore...
                        wdo('mismatch between the "used" client info\'s last_update_time and the "latest"');
                        // just return empty payload, nothing was sent...
                        return [];
                    }

                    if (isset($this->clientInfoContainer[$connResourceId]['prev_payload']) &&
                        in_array(
                            (string)$this->comparePayloads(
                                $this->clientInfoContainer[$connResourceId]['prev_payload'],
                                $payload
                            ),
                            [
                                EPayloadDifferenceType::NO_DIFFERENCES,
                                EPayloadDifferenceType::NONESSENTIAL_DIFFERENCES
                            ]
                        )
                    ) {
                        // no essential payload differences compared to the previous one, no need to send it now
                        wdo(
                            'no essential payload differences compared to the previous one, ' .
                            'no need to send it now'
                        );
                        return []; // no need to send
                    }

                    $this->clientInfoContainer[$connResourceId]['prev_payload'] = $payload;
                    $this->clientInfoContainer[$connResourceId]['last_update_time'] = $payload['update_time'];
                    $json = json_encode([
                        "success" => true,
                        "message" => null,
                        "payload" => $payload
                    ]);
                    wdo('send payload to: ' . $connResourceId);
                    $this->clients[$connResourceId]->send($json);
                    return $payload;
                });
            }
        }
        return all($promises);
    }

    private function comparePayloads(array $p1, array $p2): EPayloadDifferenceType
    {
        // any state change is essential
        if ($p1['tick']['state'] !== $p2['tick']['state']) {
            return new EPayloadDifferenceType(EPayloadDifferenceType::ESSENTIAL_DIFFERENCES);
        }

        // remember for later
        $p1TickEraTimeleft = $p1['tick']['era_timeleft'] ?? 0;
        $p2TickEraTimeleft = $p2['tick']['era_timeleft'] ?? 0;
        $eraTimeLeftDiff = abs($p1TickEraTimeleft - $p2TickEraTimeleft);

        // if there are any other changes then "time" fields, it is essential
        unset(
            $p1['prev_update_time'],
            $p1['update_time'],
            $p1TickEraTimeleft,
            $p2['prev_update_time'],
            $p2['update_time'],
            $p2TickEraTimeleft
        );
        if (0 != strcmp(json_encode($p1), json_encode($p2))) {
            return new EPayloadDifferenceType(EPayloadDifferenceType::ESSENTIAL_DIFFERENCES);
        }

        // or if the difference in era_timeleft is larger than self::LATEST_CLIENT_UPDATE_SPEED
        if ($eraTimeLeftDiff > self::LATEST_CLIENT_UPDATE_SPEED) {
            return new EPayloadDifferenceType(EPayloadDifferenceType::ESSENTIAL_DIFFERENCES);
        }

        return $eraTimeLeftDiff < PHP_FLOAT_EPSILON ?
            // note that prev_update_time and update_time are not part of the difference comparison
            new EPayloadDifferenceType(EPayloadDifferenceType::NO_DIFFERENCES) :
            // era time is different, but nonessential
            new EPayloadDifferenceType(EPayloadDifferenceType::NONESSENTIAL_DIFFERENCES);
    }

    private function repeatedTickFunction(LoopInterface $loop): Closure
    {
        return function () use ($loop) {
            $startTime = microtime(true);
            assertFulfilled(
                $this->tick()
                    ->then(function (array $tickGameSessionIds) {
                        wdo('just finished tick for game session ids: ' . implode(', ', $tickGameSessionIds));
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
                        wdo('starting new future tick');
                        $loop->futureTick($this->repeatedTickFunction($loop));
                        return;
                    }
                    $waitingSec = self::TICK_MIN_INTERVAL_SEC - $elapsedSec;
                    wdo('awaiting new future tick for ' . $waitingSec . ' sec');
                    $loop->addTimer($waitingSec, function () use ($loop) {
                        wdo('starting new future tick');
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
                        wdo('just finished "latest" for connections: ' . implode(', ', array_keys($payloadContainer)));
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
                        wdo('starting new future "latest"');
                        $loop->futureTick($this->repeatedLatestFunction($loop));
                        return;
                    }
                    $waitingSec = self::LATEST_MIN_INTERVAL_SEC - $elapsedSec;
                    wdo('awaiting new future "latest" for ' . $waitingSec . ' sec');
                    $loop->addTimer($waitingSec, function () use ($loop) {
                        wdo('starting new future "latest"');
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

        // do a dummy SELECT 1 query every 4 hours to prevent the "wait_timeout" of mysql (Default is 8 hours).
        //  if the wait timeout would go off, the database connection will be broken, and the error
        //  "2006 MySQL server has gone away" will appear.
        $loop->addPeriodicTimer(14400, function () {
            $promises = [];
            foreach ($this->gameInstances as $gameSessionId => $game) {
                $promises[$gameSessionId] = $game->doDummyQuery();
            }
            assertFulfilled(all($promises));
        });
    }

    /**
     * @throws Exception
     */
    private function getGame(int $gameSessionId): Game
    {
        if (!array_key_exists($gameSessionId, $this->gameInstances)) {
            $game = new Game();
            $game->setGameSessionId($gameSessionId);
            $game->setAsyncDatabase(
                AsyncDatabase::createGameSessionConnection($this->loop, $gameSessionId)
            );

            // do some PRE CACHING calls
            $game->GetWatchdogAddress(true);
            $game->LoadConfigFile();

            $this->gameInstances[$gameSessionId] = $game;
        }
        return $this->gameInstances[$gameSessionId];
    }

    private function getSecurity(int $gameSessionId): Security
    {
        if (!array_key_exists($gameSessionId, $this->securityInstances)) {
            $security = new Security();
            $security->setGameSessionId($gameSessionId);
            $security->setAsyncDatabase(
                AsyncDatabase::createGameSessionConnection($this->loop, $gameSessionId)
            );
            $this->securityInstances[$gameSessionId] = $security;
        }
        return $this->securityInstances[$gameSessionId];
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        if ($event instanceof NameAwareEvent) {
            return parent::dispatch($event, $event->getEventName());
        }
        return parent::dispatch($event, $eventName);
    }

    private function getClientInfoPerSessionCollection(): Collection
    {
        return collect($this->clientInfoContainer)
            ->groupBy(
                function ($value, $key) {
                    return $this->clientHeaders[$key][self::HEADER_GAME_SESSION_ID];
                },
                true
            );
    }
}
