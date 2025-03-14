<?php
namespace App\Domain\WsServer;

use App\Domain\API\v1\Security;
use App\Domain\Common\GetConstantsTrait;
use App\Domain\Common\Stopwatch\Stopwatch;
use App\Domain\Event\NameAwareEvent;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\DoctrineMigrationsDependencyFactoryHelper;
use App\Domain\WsServer\Plugins\PluginInterface;
use App\Security\BearerTokenValidator;
use Drift\DBAL\Connection;
use Exception;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Collection;
use PDOException;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Stream\ReadableResourceStream;
use Symfony\Component\EventDispatcher\EventDispatcher;

class WsServer extends EventDispatcher implements
    WsServerEventDispatcherInterface,
    ClientHeaderKeys,
    MessageComponentInterface,
    ClientConnectionResourceManagerInterface,
    ServerManagerInterface,
    WsServerInterface
{
    use GetConstantsTrait;

    const WS_SERVER_ADDRESS_MODIFICATION_NONE = 'none';
    const WS_SERVER_ADDRESS_MODIFICATION_ADD_GAME_SESSION_ID_TO_PORT = 'add_game_session_id_to_port';
    const WS_SERVER_ADDRESS_MODIFICATION_ADD_GAME_SESSION_ID_TO_URI = 'add_game_session_id_to_uri';

    private ?string $id = null;
    private ?int $gameSessionIdFilter = null;

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
    private array $pluginsToRegister = [];

    private DoctrineMigrationsDependencyFactoryHelper $doctrineMigrationsDependencyFactoryHelper;
    private ?Stopwatch $stopwatch = null;

    public function getDoctrineMigrationsDependencyFactoryHelper(): DoctrineMigrationsDependencyFactoryHelper
    {
        return $this->doctrineMigrationsDependencyFactoryHelper;
    }

    public function __construct(
        DoctrineMigrationsDependencyFactoryHelper $doctrineMigrationsDependencyFactoryHelper,
        // below is required by legacy to be auto-wired
        \App\Domain\API\APIHelper $apiHelper
    ) {
        $this->doctrineMigrationsDependencyFactoryHelper = $doctrineMigrationsDependencyFactoryHelper;

        // for backwards compatibility, to prevent missing request data errors
        $_SERVER['REQUEST_URI'] = '';

        parent::__construct();
    }

    public function getStopwatch(): ?Stopwatch
    {
        return $this->stopwatch;
    }

    public function setStopwatch(?Stopwatch $stopwatch): self
    {
        $this->stopwatch = $stopwatch;
        return $this;
    }

    public function setGameSessionIdFilter(?int $gameSessionIdFilter): self
    {
        $this->gameSessionIdFilter = $gameSessionIdFilter;
        return $this;
    }

    public function getId(): string
    {
        return $this->id ?? self::getWsServerURLBySessionId($this->gameSessionIdFilter ?? 0);
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
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

    public function onOpen(ConnectionInterface $conn): void
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

        wdo(
            'Client headers:'.PHP_EOL.
            implode(PHP_EOL, collect($headers)->mapWithKeys(fn($x, $k) => [$k => "$k=$x"])->all())
        );

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
        $headers[self::HEADER_KEY_GAME_SESSION_ID] = (int)$headers[self::HEADER_KEY_GAME_SESSION_ID];
        $this->clientHeaders[$conn->resourceId] = $headers;
        if (!(new BearerTokenValidator())->setTokenFromHeader($headers[self::HEADER_KEY_MSP_API_TOKEN])->validate()) {
            wdo('not a valid token, connection not allowed');
            $conn->close();
            return;
        }

        $this->clients[$conn->resourceId] = $conn;
        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_CONNECTED, $conn->resourceId, $headers));
    }

    public function onMessage(ConnectionInterface $from, $msg): void
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

    public function onClose(ConnectionInterface $conn): void
    {
        $conn = new WsServerConnection($conn);
        unset($this->clients[$conn->resourceId]);
        unset($this->clientInfoContainer[$conn->resourceId]);
        unset($this->clientHeaders[$conn->resourceId]);
        unset($this->securityInstances[$conn->resourceId]);

        $this->dispatch(new NameAwareEvent(self::EVENT_ON_CLIENT_DISCONNECTED, $conn->resourceId));
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
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

    public function registerPlugin(PluginInterface $plugin): self
    {
        $plugin
            ->setGameSessionIdFilter($this->gameSessionIdFilter)
            ->setStopwatch($this->stopwatch)
            ->setClientConnectionResourceManager($this)
            ->setServerManager($this)
            ->setWsServer($this);

        // wait for loop to be registered
        if (null === $this->loop) {
            $this->pluginsToRegister[$plugin->getName()] = $plugin;
            return $this;
        }

        $plugin->registerToLoop($this->loop);
        $this->plugins[$plugin->getName()] = $plugin;
        return $this;
    }

    public function unregisterPlugin(PluginInterface $plugin): void
    {
        unset($this->plugins[$plugin->getName()]);
        unset($this->pluginsToRegister[$plugin->getName()]);
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
            $qb->andWhere(
                $qb->expr()->or(
                    $qb->expr()->in(
                        'game_state',
                        $qb->createPositionalParameter(['play', 'fastforward' ,'simulation', 'pause', 'setup'])
                    )
                )
            );
        }
        return $connection->query($qb);
    }

    public function registerLoop(LoopInterface $loop): self
    {
        $this->loop = $loop;

        // register plugins to loop
        while (!empty($this->pluginsToRegister)) {
            $plugin = array_pop($this->pluginsToRegister);
            $plugin->registerToLoop($this->loop);
            $this->plugins[$plugin->getName()] = $plugin;
        }

        $loop->addPeriodicTimer(2, function () {
            $this->dispatch(new NameAwareEvent(WsServerEventDispatcherInterface::EVENT_ON_STATS_UPDATE));
        });

        $stdin = new ReadableResourceStream(STDIN, $loop);
        $stdin->on('data', function ($input) {
            if ($input === 'n'.PHP_EOL) {
                $this->dispatch(new NameAwareEvent(WsServerEventDispatcherInterface::EVENT_ON_NEXT_VIEW));
            }
        });

        return $this;
    }

    /**
     * @throws Exception
     */
    public function getGameSessionDbConnection(int $gameSessionId): Connection
    {
        return ConnectionManager::getInstance()->getCachedAsyncGameSessionDbConnection($this->loop, $gameSessionId);
    }

    /**
     * @throws Exception
     */
    public function getServerManagerDbConnection(): Connection
    {
        return ConnectionManager::getInstance()->getCachedAsyncServerManagerDbConnection($this->loop);
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

    public static function getWsServerURLBySessionId(
        int $sessionId = 0,
        string $hostDefaultValue = 'localhost'
    ): string {
        $addressModificationEnvName = 'URL_WS_SERVER_ADDRESS_MODIFICATION';
        $addressModificationEnvValue = ($_ENV['URL_WS_SERVER_ADDRESS_MODIFICATION'] ?? null) ?: 'none';
        $port = ($_ENV['URL_WS_SERVER_PORT'] ?? null) ?: 45001;
        $uri = $_ENV['URL_WS_SERVER_URI'] ?? '';
        switch ($addressModificationEnvValue) {
            case self::WS_SERVER_ADDRESS_MODIFICATION_ADD_GAME_SESSION_ID_TO_PORT:
                $port += $sessionId;
                break;
            case self::WS_SERVER_ADDRESS_MODIFICATION_ADD_GAME_SESSION_ID_TO_URI:
                $uri = rtrim($uri, '/') . '/' . $sessionId . '/';
                break;
            case self::WS_SERVER_ADDRESS_MODIFICATION_NONE:
                break;
            default:
                throw new \http\Exception\UnexpectedValueException(
                    sprintf(
                        'Encountered unexpected value for environmental variable %s: %s. Value must be: %s',
                        $addressModificationEnvName,
                        $addressModificationEnvValue,
                        implode(', ', self::getConstants())
                    )
                );
        }
        $scheme = str_replace('://', '', ($_ENV['URL_WS_SERVER_SCHEME'] ?? null) ?: 'ws').'://';
        $host = ($_ENV['URL_WS_SERVER_HOST'] ?? null) ?: $hostDefaultValue;
        return $scheme.$host.':'.$port.$uri;
    }
}
