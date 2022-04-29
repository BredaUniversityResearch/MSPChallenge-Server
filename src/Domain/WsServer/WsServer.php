<?php
namespace App\Domain\WsServer;

use App\Domain\API\v1\Security;
use App\Domain\Event\NameAwareEvent;
use App\Domain\Helper\AsyncDatabase;
use App\Domain\Helper\Util;
use App\Domain\WsServer\Plugins\PluginInterface;
use Drift\DBAL\Connection;
use Drift\DBAL\ConnectionPool;
use Drift\DBAL\ConnectionWorker;
use Drift\DBAL\Result;
use Exception;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Collection;
use PDOException;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function App\assertFulfilled;
use function App\parallel;
use function App\tpf;
use function React\Promise\all;

class WsServer extends EventDispatcher implements
    WsServerEventDispatcherInterface,
    ClientHeaderKeys,
    MessageComponentInterface,
    MeasurementCollectionManagerInterface,
    ClientConnectionResourceManagerInterface,
    ServerManagerInterface
{
    private ?int $gameSessionId = null;
    private array $stats = [];
    private array $medianValues = [];

    private ?LoopInterface $loop = null;

    /**
     * @var WsServerConnection[]
     */
    private array $clients = [];
    private array $clientInfoContainer = [];
    private array $clientHeaders = [];

    /**
     * @var Connection[]
     */
    private array $databaseInstances = [];

    /**
     * @var Security[]
     */
    private array $securityInstances = [];

    /**
     * @var PluginInterface[]
     */
    private array $plugins = [];

    /**
     * @var PluginInterface[]
     */
    private array $pluginsUnregistered = [];

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

    public function getClientHeaders(int $connResourceId): ?array
    {
        if (!array_key_exists($connResourceId, $this->clientHeaders)) {
            return null;
        }
        return $this->clientHeaders[$connResourceId];
    }

    public function getClientInfo(int $connResourceId): ?array
    {
        if (!array_key_exists($connResourceId, $this->clientInfoContainer)) {
            return null;
        }
        return $this->clientInfoContainer[$connResourceId];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $conn = new WsServerConnection($conn);
        $conn->setEventDispatcher($this);
        $httpRequest = $conn->httpRequest;
        /** @var Request $httpRequest */
        $headers = collect($httpRequest->getHeaders())
            ->map(function (array $value) {
                return $value[0];
            })
            ->all();

        if (!array_key_exists(self::HEADER_KEY_GAME_SESSION_ID, $headers) ||
            !array_key_exists(self::HEADER_KEY_MSP_API_TOKEN, $headers)) {
            // required headers are not there, do not allow connection
            wdo('required headers are not there, do not allow connection');
            $conn->close();
            return;
        }
        $gameSessionId = $headers[self::HEADER_KEY_GAME_SESSION_ID];
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
            $headers[self::HEADER_KEY_MSP_API_TOKEN]
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
        unset($this->securityInstances[$conn->resourceId]);

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

    public function startMeasurementCollection(string $name)
    {
        // reset
        $this->stats[$name.'.worst_of_prev_tick'] = 0;
    }

    public function addToMeasurementCollection(string $name, string $measurementId, float $measurementTime)
    {
        // init
        $this->stats[$name.'.worst_of_prev_tick'] ??= 0;
        $this->stats[$name.'.median'] ??= 0;

        $this->medianValues[$name][$measurementId] = $measurementTime;
        $this->stats[$name.'.worst_of_prev_tick'] = max(
            $this->stats[$name.'.worst_of_prev_tick'] ?? 0,
            $measurementTime
        );
        $this->stats[$name.'.worst_ever'] = max($this->stats[$name.'.worst_ever'] ?? 0, $measurementTime);
    }

    public function endMeasurementCollection(string $name)
    {
        if (!array_key_exists($name.'.median', $this->stats)) {
            return;
        }
        $this->stats[$name.'.median'] = Util::getMedian($this->medianValues[$name]);
    }

    public function registerPlugin(PluginInterface $plugin)
    {
        $plugin
            ->setGameSessionId($this->gameSessionId)
            ->setMeasurementCollectionManager($this)
            ->setClientConnectionResourceManager($this)
            ->setServerManager($this);

        $this->pluginsUnregistered[$plugin->getName()] = $plugin;

        // wait for loop to be registered
        if (null === $this->loop) {
            return;
        }

        // register plugins to loop
        while (!empty($this->pluginsUnregistered)) {
            $plugin = array_pop($this->pluginsUnregistered);
            $plugin->registerLoop($this->loop);
            $this->plugins[$plugin->getName()] = $plugin;
        }
    }

    public function getClientInfoPerSessionCollection(): Collection
    {
        return collect($this->getClientInfoContainer())
            ->groupBy(
                function ($value, $key) {
                    return $this->getClientHeaders($key)[
                        ClientHeaderKeys::HEADER_KEY_GAME_SESSION_ID
                    ];
                },
                true
            );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getGameSessionIds(): PromiseInterface
    {
        $connection = AsyncDatabase::createServerManagerConnection(Loop::get());
        $qb = $connection->createQueryBuilder();
        return $connection->query(
            $qb
                ->select('id')
                ->from('game_list')
        );
    }

    public function registerLoop(LoopInterface $loop)
    {
        $this->loop = $loop;

        // register plugins to loop
        while (!empty($this->pluginsUnregistered)) {
            $plugin = array_pop($this->pluginsUnregistered);
            $plugin->registerLoop($this->loop);
            $this->plugins[$plugin->getName()] = $plugin;
        }

        // do a dummy SELECT 1 query every 4 hours to prevent the "wait_timeout" of mysql (Default is 8 hours).
        //  if the wait timeout would go off, the database connection will be broken, and the error
        //  "2006 MySQL server has gone away" will appear.
        $loop->addPeriodicTimer(14400, function () {
            assertFulfilled($this->doDummyQuery());
        });

        $loop->addPeriodicTimer(2, function () {
            $this->dispatch(new NameAwareEvent(WsServerEventDispatcherInterface::EVENT_ON_STATS_UPDATE));
        });
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function doDummyQuery(): PromiseInterface
    {
        return $this->getGameSessionIds()
            ->then(function (Result $result) {
                $gameSessionIds = collect($result->fetchAllRows() ?? [])
                    ->keyBy('id')
                    ->map(function ($row) {
                        return $row['id'];
                    });
                $promises = [];
                foreach ($gameSessionIds as $gameSessionId) {
                    $connection = $this->getAsyncDatabase($gameSessionId);
                    $connections = [];
                    if ($connection instanceof ConnectionPool) {
                        $connections = collect($connection->getWorkers())
                            ->map(function (ConnectionWorker $worker) {
                                return $worker->getConnection();
                            })
                            ->all();
                    } else { // if ($connection is SingleConnection)
                        $connections[] = $connection;
                    }
                    $toPromiseFunctions = [];
                    foreach ($connections as $connection) {
                        $toPromiseFunctions[] = tpf(function () use ($connection) {
                            $qb = $connection->createQueryBuilder();
                            return $connection->query(
                                $qb->select('1')
                            );
                        });
                    }
                    $promises[$gameSessionId] = parallel($toPromiseFunctions);
                }
                return all($promises);
            });
    }

    public function getAsyncDatabase(int $gameSessionId): Connection
    {
        if (!array_key_exists($gameSessionId, $this->databaseInstances)) {
            $this->databaseInstances[$gameSessionId] =
                AsyncDatabase::createGameSessionConnection($this->loop, $gameSessionId);
        }
        return $this->databaseInstances[$gameSessionId];
    }

    public function getSecurity(int $connResourceId): Security
    {
        $gameSessionId = $this->clientHeaders[$connResourceId][self::HEADER_KEY_GAME_SESSION_ID];
        if (!array_key_exists($connResourceId, $this->securityInstances)) {
            $security = new Security();
            $security->setAsync(true);
            $security->setGameSessionId($gameSessionId);
            $security->setAsyncDatabase($this->getAsyncDatabase($gameSessionId));
            $security->setToken($this->clientHeaders[$connResourceId][self::HEADER_KEY_MSP_API_TOKEN]);
            $this->securityInstances[$connResourceId] = $security;
        }
        return $this->securityInstances[$connResourceId];
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        if ($event instanceof NameAwareEvent) {
            // let plugins know what happened
            foreach ($this->plugins as $plugin) {
                $plugin->onWsServerEventDispatched($event);
            }
            return parent::dispatch($event, $event->getEventName());
        }
        return parent::dispatch($event, $eventName);
    }

    public function getClientConnection(int $connResourceId): ?WsServerConnection
    {
        if (!array_key_exists($connResourceId, $this->clients)) {
            return null;
        }
        return $this->clients[$connResourceId];
    }

    public function getClientHeadersContainer(): array
    {
        return $this->clientHeaders;
    }

    public function getClientInfoContainer(): array
    {
        return $this->clientInfoContainer;
    }

    public function setClientInfo(int $connResourceId, string $clientInfoKey, $clientInfoValue): void
    {
        $this->clientInfoContainer[$connResourceId][$clientInfoKey] = $clientInfoValue;
    }
}
