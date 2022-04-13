<?php
namespace App\Domain\WsServer;

use App\Domain\API\v1\Batch;
use App\Domain\API\v1\Game;
use App\Domain\API\v1\Security;
use App\Domain\Event\NameAwareEvent;
use App\Domain\Helper\AsyncDatabase;
use App\Domain\Helper\Util;
use Closure;
use Drift\DBAL\Connection;
use Exception;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Collection;
use PDOException;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;
use function App\assertFulfilled;
use function React\Promise\all;
use function React\Promise\reject;

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
    const LATEST_CLIENT_UPDATE_SPEED = 60.0;

    const EVENT_ON_CLIENT_CONNECTED = 'EVENT_ON_CLIENT_CONNECTED';
    const EVENT_ON_CLIENT_DISCONNECTED = 'EVENT_ON_CLIENT_DISCONNECTED';
    const EVENT_ON_CLIENT_ERROR = 'EVENT_ON_CLIENT_ERROR';
    const EVENT_ON_CLIENT_MESSAGE_RECEIVED = 'EVENT_ON_CLIENT_MESSAGE_RECEIVED';
    const EVENT_ON_CLIENT_MESSAGE_SENT = 'EVENT_ON_CLIENT_MESSAGE_SENT';
    const EVENT_ON_STATS_UPDATE = 'EVENT_ON_STATS_UPDATE';

    const TICK_MIN_INTERVAL_SEC = 2;
    const LATEST_MIN_INTERVAL_SEC = 0.2;
    const EXECUTE_BATCHES_MIN_INTERVAL_SEC = 1;

    private ?int $gameSessionId = null;
    private array $stats = [];
    private array $medianValues = [];

    private ?LoopInterface $loop = null;
    private array $clients = [];
    private array $clientInfoContainer = [];
    private array $clientHeaders = [];

    /**
     * @var Connection[]
     */
    private array $databaseInstances = [];

    /**
     * @var Game[]
     */
    private array $gameInstances = [];

    /**
     * @var Security[]
     */
    private array $securityInstances = [];

    /**
     * @var Batch[]
     */
    private array $batchesInstances = [];

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
        $conn = new WsServerConnection($conn);
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
        $gameSessionId = $headers[self::HEADER_GAME_SESSION_ID];
        if (null != $this->gameSessionId && $this->gameSessionId != $gameSessionId) {
            // do not connect this client, client is from another game session
            wdo('do not connect this client, client is from another game session');
            $conn->close();
            return;
        }

        // since we need client headers to create a Base instances, set it before calling getSecurity(...)
        $this->clientHeaders[$conn->resourceId] = $headers;

        $accessTimeRemaining = 0;
        if (false === $this->getSecurity($conn->resourceId)->validateAccess(
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
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_CONNECTED, $conn->resourceId, $headers));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $from = new WsServerConnection($from);
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
        $conn = new WsServerConnection($conn);
        unset($this->clients[$conn->resourceId]);
        unset($this->clientInfoContainer[$conn->resourceId]);
        unset($this->clientHeaders[$conn->resourceId]);
        unset($this->gameInstances[$conn->resourceId]);
        unset($this->securityInstances[$conn->resourceId]);
        unset($this->batchesInstances[$conn->resourceId]);

        // clean up "finishedTicksGameSessionIds" by active game session ids.
        $clientInfoPerSessionContainer = $this->getClientInfoPerSessionCollection()->all();
        $this->finishedTicksGameSessionIds = array_diff_key(
            $this->finishedTicksGameSessionIds,
            $clientInfoPerSessionContainer
        );

        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_DISCONNECTED, $conn->resourceId));
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        $conn = new WsServerConnection($conn);
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

            // just pick the first connection Game instance using this game session
            /** @var Collection $clientInfoContainer */
            $connResourceId = key($clientInfoContainer->all());

            $promises[$gameSessionId] = $this->getGame($connResourceId)->Tick(!empty($_ENV['WS_SERVER_DEBUG_OUTPUT']))
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
                if (false === $this->getSecurity($connResourceId)->validateAccess(
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
                $promises[$connResourceId] = $this->getGame($connResourceId)->Latest(
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
                        // disconnected while running this async code, nothing was sent
                        wdo('disconnected while running this async code, nothing was sent');
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
                        'header_type' => 'Game/Latest',
                        'header_data' => null,
                        'success' => true,
                        'message' => null,
                        'payload' => $payload
                    ]);
                    wdo('send payload to: ' . $connResourceId);
                    $this->clients[$connResourceId]->send($json);
                    return $payload;
                });
            }
        }
        return all($promises);
    }

    private function executeBatches(): PromiseInterface
    {
        wdo('starting "executeBatches"');
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
        $this->statsLoopStart('executeBatches');
        foreach ($clientInfoPerSessionContainer as $gameSessionId => $clientInfoContainer) {
            if (!array_key_exists($gameSessionId, $this->finishedTicksGameSessionIds)) {
                // wait for a first finished tick
                wdo('wait for a first finished tick: ' . $gameSessionId);
                continue;
            }

            foreach ($clientInfoContainer as $connResourceId => $clientInfo) {
                $timeStart = microtime(true);
                wdo('Starting "executeBatches" for: ' . $connResourceId);
                $promises[$connResourceId] = $this->getBatch($connResourceId)->executeNextQueuedBatchFor(
                    $clientInfo['team_id'],
                    $clientInfo['user']
                )
                ->then(
                    function (array $batchResultContainer) use ($connResourceId, $timeStart, $clientInfo) {
                        wdo('Created "executeBatches" payload for: ' . $connResourceId);
                        $this->statsLoopRegister('executeBatches', $connResourceId, microtime(true) - $timeStart);
                        if (empty($batchResultContainer)) {
                            return [];
                        }
                        if (!array_key_exists($connResourceId, $this->clients)) {
                            // disconnected while running this async code, nothing was sent
                            wdo('disconnected while running this async code, nothing was sent');
                            $e = new ClientDisconnectedException();
                            $e->setConnResourceId($connResourceId);
                            throw $e;
                        }

                        $batchId = key($batchResultContainer);
                        $batchResult = current($batchResultContainer);

                        $json = json_encode([
                            'header_type' => 'Batch/ExecuteBatch',
                            'header_data' => [
                                'batch_id' => $batchId,
                            ],
                            'success' => true,
                            'message' => null,
                            'payload' => $batchResult
                        ]);
                        $this->clients[$connResourceId]->send($json);
                        return $batchResultContainer;
                    },
                    function ($rejection) use ($connResourceId) {
                        $reason = $rejection;
                        if ($rejection instanceof ExecuteBatchRejection) {
                            $batchId = $rejection->getBatchId();
                            $reason = $rejection->getReason();
                            $message = '';
                            if (is_string($reason)) {
                                $message = $reason;
                            }
                            while ($reason instanceof Throwable) {
                                $message .= $reason->getMessage() . PHP_EOL;
                                $reason = $reason->getPrevious();
                            }
                            $json = json_encode([
                                'header_type' => 'Batch/ExecuteBatch',
                                'header_data' => [
                                    'batch_id' => $batchId,
                                ],
                                'success' => false,
                                'message' => $message ?: 'Unknown reason',
                                'payload' => null
                            ]);
                            $this->clients[$connResourceId]->send($json);
                            return []; // do not propagate rejection, just resolve to empty batch results
                        }
                        return reject($reason);
                    }
                );
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
            $p1['tick']['era_timeleft'],
            $p2['prev_update_time'],
            $p2['update_time'],
            $p2TickEraTimeleft,
            $p2['tick']['era_timeleft']
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

    private function createTickPromiseFunction(): Closure
    {
        return function () {
            return $this->tick()
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
                });
        };
    }

    private function createLatestPromiseFunction(): Closure
    {
        return function () {
            return $this->latest()
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
                    wdo(json_encode($payloadContainer));
                    $this->statsLoopEnd('latest');
                })
                ->otherwise(function ($reason) {
                    if ($reason instanceof ClientDisconnectedException) {
                        return null;
                    }
                    return reject($reason);
                });
        };
    }

    private function createExecuteBatchesPromiseFunction(): Closure
    {
        return function () {
            return $this->executeBatches()
                ->then(function (array $clientToBatchResultContainer) {
                    wdo(
                        'just finished "executeBatches" for connections: ' .
                        implode(', ', array_keys($clientToBatchResultContainer))
                    );
                    $clientToBatchResultContainer = array_filter($clientToBatchResultContainer);
                    if (!empty($clientToBatchResultContainer)) {
                        $this->dispatch(
                            new NameAwareEvent(
                                self::EVENT_ON_CLIENT_MESSAGE_SENT,
                                array_keys($clientToBatchResultContainer),
                                $clientToBatchResultContainer
                            )
                        );
                    }
                    wdo(json_encode($clientToBatchResultContainer));
                    $this->statsLoopEnd('executeBatches');
                })
                ->otherwise(function ($reason) {
                    if ($reason instanceof ClientDisconnectedException) {
                        return null;
                    }
                    return reject($reason);
                });
        };
    }

    public function registerLoop(LoopInterface $loop)
    {
        $this->loop = $loop;
        $loop->futureTick($this->createRepeatedFunction(
            $loop,
            'tick',
            $this->createTickPromiseFunction(),
            self::TICK_MIN_INTERVAL_SEC
        ));
        $loop->futureTick($this->createRepeatedFunction(
            $loop,
            'latest',
            $this->createLatestPromiseFunction(),
            self::LATEST_MIN_INTERVAL_SEC
        ));
        $loop->futureTick($this->createRepeatedFunction(
            $loop,
            'executeBatches',
            $this->createExecuteBatchesPromiseFunction(),
            self::EXECUTE_BATCHES_MIN_INTERVAL_SEC
        ));

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

    private function getAsyncDatabase(int $gameSessionId): Connection
    {
        if (!array_key_exists($gameSessionId, $this->databaseInstances)) {
            $this->databaseInstances[$gameSessionId] =
                AsyncDatabase::createGameSessionConnection($this->loop, $gameSessionId);
        }
        return $this->databaseInstances[$gameSessionId];
    }

    /**
     * @throws Exception
     */
    private function getGame(int $connResourceId): Game
    {
        $gameSessionId = $this->clientHeaders[$connResourceId][self::HEADER_GAME_SESSION_ID];
        if (!array_key_exists($connResourceId, $this->gameInstances)) {
            $game = new Game();
            $game->setAsync(true);
            $game->setGameSessionId($gameSessionId);
            $game->setAsyncDatabase($this->getAsyncDatabase($gameSessionId));
            $game->setToken($this->clientHeaders[$connResourceId][self::HEADER_MSP_API_TOKEN]);

            // do some PRE CACHING calls
            $game->GetWatchdogAddress(true);
            $game->LoadConfigFile();

            $this->gameInstances[$connResourceId] = $game;
        }
        return $this->gameInstances[$connResourceId];
    }

    private function getSecurity(int $connResourceId): Security
    {
        $gameSessionId = $this->clientHeaders[$connResourceId][self::HEADER_GAME_SESSION_ID];
        if (!array_key_exists($connResourceId, $this->securityInstances)) {
            $security = new Security();
            $security->setAsync(true);
            $security->setGameSessionId($gameSessionId);
            $security->setAsyncDatabase($this->getAsyncDatabase($gameSessionId));
            $security->setToken($this->clientHeaders[$connResourceId][self::HEADER_MSP_API_TOKEN]);
            $this->securityInstances[$connResourceId] = $security;
        }
        return $this->securityInstances[$connResourceId];
    }

    private function getBatch(int $connResourceId): Batch
    {
        $gameSessionId = $this->clientHeaders[$connResourceId][self::HEADER_GAME_SESSION_ID];
        if (!array_key_exists($connResourceId, $this->batchesInstances)) {
            $batch = new Batch();
            $batch->setAsync(true);
            $batch->setGameSessionId($gameSessionId);
            $batch->setAsyncDatabase($this->getAsyncDatabase($gameSessionId));
            $batch->setToken($this->clientHeaders[$connResourceId][self::HEADER_MSP_API_TOKEN]);
            $this->batchesInstances[$connResourceId] = $batch;
        }
        return $this->batchesInstances[$connResourceId];
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

    private function createRepeatedFunction(
        LoopInterface $loop,
        string $name,
        Closure $promiseFunction,
        float $minIntervalSec
    ): Closure {
        return function () use ($loop, $promiseFunction, $name, $minIntervalSec) {
            $startTime = microtime(true);
            assertFulfilled(
                $promiseFunction(),
                $this->createRepeatedOnFulfilledFunction(
                    $loop,
                    $name,
                    $startTime,
                    $minIntervalSec,
                    $this->createRepeatedFunction($loop, $name, $promiseFunction, $minIntervalSec)
                )
            );
        };
    }

    private function createRepeatedOnFulfilledFunction(
        LoopInterface $loop,
        string $name,
        float $startTime,
        float $minIntervalSec,
        Closure $repeatedFunction
    ): Closure {
        return function () use ($loop, $startTime, $minIntervalSec, $name, $repeatedFunction) {
            $elapsedSec = (microtime(true) - $startTime) * 0.000001;
            if ($elapsedSec > $minIntervalSec) {
                wdo('starting new future "' . $name .'"');
                $loop->futureTick($repeatedFunction);
                return;
            }
            $waitingSec = $minIntervalSec - $elapsedSec;
            wdo('awaiting new future "' . $name . '" for ' . $waitingSec . ' sec');
            $loop->addTimer($waitingSec, function () use ($loop, $name, $repeatedFunction) {
                wdo('starting new future "' . $name . '"');
                $loop->futureTick($repeatedFunction);
            });
        };
    }
}
