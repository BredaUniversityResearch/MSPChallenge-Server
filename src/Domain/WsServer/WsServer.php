<?php
namespace App\Domain\WsServer;

use App\Domain\API\v1\Security;
use App\Domain\Event\NameAwareEvent;
use App\Domain\Helper\AsyncDatabase;
use App\Domain\Helper\Util;
use App\Domain\WsServer\Plugins\PluginInterface;
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

class WsServer extends EventDispatcher implements
    WsServerEventDispatcherInterface,
    ClientHeaderKeys,
    MessageComponentInterface,
    MeasurementCollectionManagerInterface,
    ClientConnectionResourceManagerInterface,
    ServerManagerInterface,
    WsServerInterface
{
    private ?int $gameSessionIdFilter = null;
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

    public function setGameSessionIdFilter(int $gameSessionIdFilter): self
    {
        $this->gameSessionIdFilter = $gameSessionIdFilter;
        return $this;
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
        if (null != $this->gameSessionIdFilter && $this->gameSessionIdFilter != $gameSessionId) {
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

    public function addToMeasurementCollection(string $name, string $measurementId, float $measurementTime)
    {
        // init
        $this->stats[$name.'.worst'] ??= 0;
        $this->stats[$name.'.median'] ??= 0;

        $this->medianValues[$name][$measurementId] = $measurementTime;
        // only keep the last 20 measures
        foreach ($this->medianValues as &$medianValues) {
            $medianValues = array_slice($medianValues, -20, null, true);
        }
        unset($medianValues);

        // reset "worst" after 10 s
        static $startTime = null;
        if ($startTime == null) {
            $startTime = microtime(true);
        }
        if (microtime(true) - $startTime > 10) {
            $startTime = microtime(true);
            $this->stats[$name.'.worst'] = 0;
        }

        // update measurements
        $this->stats[$name.'.worst'] = max(
            $this->stats[$name.'.worst'] ?? 0,
            $measurementTime
        );
        $this->stats[$name.'.worst_ever'] = max($this->stats[$name.'.worst_ever'] ?? 0, $measurementTime);
        $this->stats[$name.'.median'] = Util::getMedian($this->medianValues[$name]);
    }

    public function registerPlugin(PluginInterface $plugin): self
    {
        $plugin
            ->setGameSessionIdFilter($this->gameSessionIdFilter)
            ->setMeasurementCollectionManager($this)
            ->setClientConnectionResourceManager($this)
            ->setServerManager($this)
            ->setWsServer($this);

        // wait for loop to be registered
        if (null === $this->loop) {
            $this->pluginsUnregistered[$plugin->getName()] = $plugin;
            return $this;
        }

        $plugin->registerToLoop($this->loop);
        $this->plugins[$plugin->getName()] = $plugin;
        return $this;
    }

    public function unregisterPlugin(PluginInterface $plugin)
    {
        unset($this->plugins[$plugin->getName()]);
        unset($this->pluginsUnregistered[$plugin->getName()]);
        if (null === $this->loop) {
            return;
        }
        $plugin->unregisterFromLoop($this->loop);
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
    public function getGameSessionIds(bool $onlyPlaying = false): PromiseInterface
    {
        $connection = $this->getServerManagerDbConnection();
        $qb = $connection->createQueryBuilder();
        $qb
            ->select('id')
            ->from('game_list')
            ->where($qb->expr()->eq('session_state', $qb->createPositionalParameter('healthy')));
        if ($onlyPlaying) {
            $qb->andWhere($qb->expr()->in(
                'game_state',
                $qb->createPositionalParameter([
                    'play', 'fastforward' ,'simulation'
                ])
            ));
        }
        return $connection->query($qb);
    }

    public function registerLoop(LoopInterface $loop): self
    {
        $this->loop = $loop;

        // register plugins to loop
        while (!empty($this->pluginsUnregistered)) {
            $plugin = array_pop($this->pluginsUnregistered);
            $plugin->registerToLoop($this->loop);
            $this->plugins[$plugin->getName()] = $plugin;
        }

        $loop->addPeriodicTimer(2, function () {
            $this->dispatch(new NameAwareEvent(WsServerEventDispatcherInterface::EVENT_ON_STATS_UPDATE));
        });

        return $this;
    }

    public function getGameSessionDbConnection(int $gameSessionId): Connection
    {
        return AsyncDatabase::getCachedGameSessionDbConnection($this->loop, $gameSessionId);
    }

    public function getServerManagerDbConnection(): Connection
    {
        return AsyncDatabase::getCachedServerManagerDbConnection($this->loop);
    }

    public function getSecurity(int $connResourceId): Security
    {
        $gameSessionId = $this->clientHeaders[$connResourceId][self::HEADER_KEY_GAME_SESSION_ID];
        if (!array_key_exists($connResourceId, $this->securityInstances)) {
            $security = new Security();
            $security->setAsync(true);
            $security->setGameSessionId($gameSessionId);
            $security->setAsyncDatabase($this->getGameSessionDbConnection($gameSessionId));
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
