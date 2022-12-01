<?php

namespace App\Domain\Services;

use App\Domain\Common\DatabaseDefaults;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Drift\DBAL\Connection as DriftConnection;
use Drift\DBAL\ConnectionOptions;
use Drift\DBAL\ConnectionPool;
use Drift\DBAL\ConnectionPoolOptions;
use Drift\DBAL\Credentials;
use Drift\DBAL\Driver\Mysql\MysqlDriver;
use Drift\DBAL\SingleConnection;
use Exception;
use React\EventLoop\LoopInterface;

class ConnectionManager extends DatabaseDefaults
{
    private static ?ConnectionManager $instance = null;

    /**
     * @var Connection[]
     */
    private array $dbConnections = [];
    /**
     * @var DriftConnection[]
     */
    private array $asyncDbConnections = [];

    public function __construct()
    {
        self::$instance = $this;
    }

    /**
     * @throws Exception
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getDbNames(): array
    {
        $connection = $this->getCachedServerManagerDbConnection();
        $sm = $connection->createSchemaManager();
        $dbNames = $sm->listDatabases();
        return array_diff($dbNames, [
            'information_schema', 'test', 'phpmyadmin', 'performance_schema', 'mysql'
        ]);
    }

    public function getConnectionConfig(?string $dbName = null): array
    {
        $config = [
            'driver' => 'pdo_mysql',
            'host' => $_ENV['DATABASE_HOST'] ?? self::DEFAULT_DATABASE_HOST,
            'port' => $_ENV['DATABASE_PORT'] ?? self::DEFAULT_DATABASE_PORT,
            'user' => $_ENV['DATABASE_USER'] ?? self::DEFAULT_DATABASE_USER,
            'password' => $_ENV['DATABASE_PASSWORD'] ?? self::DEFAULT_DATABASE_PASSWORD,
            'server_version' => $_ENV['DATABASE_SERVER_VERSION'] ?? self::DEFAULT_DATABASE_SERVER_VERSION,
            'charset' => $_ENV['DATABASE_CHARSET'] ?? self::DEFAULT_DATABASE_CHARSET,
            'mapping_types' => ['enum' => 'string']
        ];
        if ($dbName !== null) {
            $config['dbname'] = $dbName;
        }
        if (($_ENV['APP_ENV'] ?? null) !== 'test') {
            return $config;
        }
        # "TEST_TOKEN" is typically set by ParaTest
        $config['dbname_suffix'] = '_test%env(default::TEST_TOKEN)%';
        return $config;
    }

    public function getEntityManagerConfig(string $dbName): array
    {
        // @note(MH): You cannot enable "auto_mapping" on more than one manager at the same time
        $config['connection'] = $dbName;
        $config['mappings']['App'] = [
            'is_bundle' => false,
            'dir' => '%kernel.project_dir%/src/Entity',
            'prefix' => 'App\Entity',
            'alias' => 'App'
        ];
        $config['naming_strategy'] = 'doctrine.orm.naming_strategy.underscore';
        if (($_ENV['APP_ENV'] ?? null) !== 'prod') {
            return $config;
        }
        return array_merge($config, [
            'query_cache_driver' => [
                'type' => 'pool',
                'pool' => 'doctrine.system_cache_pool'
            ],
            'result_cache_driver' => [
                'type' => 'pool',
                'pool' => 'doctrine.result_cache_pool'
            ]
        ]);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getCachedDbConnection(string $dbName, bool $cacheRefresh = false): Connection
    {
        if ($cacheRefresh ||
            !array_key_exists($dbName, $this->dbConnections)
        ) {
            $this->dbConnections[$dbName] = $this->createDbConnection($dbName);
        }
        return $this->dbConnections[$dbName];
    }

    public function getCachedAsyncDbConnection(
        LoopInterface $loop,
        string $dbName,
        ?ConnectionOptions $options,
        bool $cacheRefresh = false
    ): DriftConnection {
        if ($cacheRefresh ||
            !array_key_exists($dbName, $this->asyncDbConnections)
        ) {
            $this->asyncDbConnections[$dbName] = $this->createAsyncDbConnection($loop, $dbName, $options);
        }
        return $this->asyncDbConnections[$dbName];
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function createDbConnection(string $dbName): Connection
    {
        $connection = DriverManager::getConnection($this->getConnectionConfig($dbName));
        $platform = $connection->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');
        return $connection;
    }

    public function createAsyncDbConnection(
        LoopInterface $loop,
        string $dbName,
        ?ConnectionOptions $options = null
    ): DriftConnection {
        $mysqlPlatform = new MySqlPlatform();
        $mysqlDriver = new MysqlDriver($loop);
        $credentials = new Credentials(
            $_ENV['DATABASE_HOST'] ?? self::DEFAULT_DATABASE_HOST,
            $_ENV['DATABASE_PORT'] ?? self::DEFAULT_DATABASE_PORT,
            $_ENV['DATABASE_USER'] ?? self::DEFAULT_DATABASE_USER,
            $_ENV['DATABASE_PASSWORD'] ?? self::DEFAULT_DATABASE_PASSWORD,
            $dbName
        );

        if ($options instanceof ConnectionPoolOptions) {
            return ConnectionPool::createConnected(
                $mysqlDriver,
                $credentials,
                $mysqlPlatform,
                $options
            );
        }

        return SingleConnection::createConnected($mysqlDriver, $credentials, $mysqlPlatform, $options);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getCachedServerManagerDbConnection(bool $cacheRefresh = false): Connection
    {
        return $this->getCachedDbConnection($this->getServerManagerDbName(), $cacheRefresh);
    }

    public function getCachedAsyncServerManagerDbConnection(
        LoopInterface $loop,
        bool $cacheRefresh = false
    ): DriftConnection {
        return $this->getCachedAsyncDbConnection($loop, $this->getServerManagerDbName(), null, $cacheRefresh);
    }

    public function getServerManagerDbName(): string
    {
        return $_ENV['DBNAME_SERVER_MANAGER'] ?? self::DEFAULT_DBNAME_SERVER_MANAGER;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function createServerManagerConnection(): Connection
    {
        return $this->createDbConnection($this->getServerManagerDbName());
    }

    public function createAsyncServerManagerConnection(LoopInterface $loop): DriftConnection
    {
        return $this->createAsyncDbConnection($loop, $this->getServerManagerDbName());
    }

    public function getGameSessionDbName(int $gameSessionId): string
    {
        return ($_ENV['DBNAME_SESSION_PREFIX'] ?? self::DEFAULT_DBNAME_SESSION_PREFIX) . $gameSessionId;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getCachedGameSessionDbConnection(
        int $gameSessionId,
        bool $cacheRefresh = false
    ): Connection {
        return $this->getCachedDbConnection($this->getGameSessionDbName($gameSessionId), $cacheRefresh);
    }

    private function getGameSessionConnectionOptions(): ConnectionOptions
    {
        // Set $keepAliveIntervalSec to 14400 = 4 hours
        // This will do a SELECT 1 query every 4 hours to prevent the "wait_timeout" of mysql (Default is 8 hours).
        // If the wait timeout would go off, the database connection will be broken, and the error
        //   "2006 MySQL server has gone away" will appear.
        return new ConnectionPoolOptions($_ENV['DATABASE_POOL_NUM_CONNECTIONS'] ?? 20, 14400);
    }

    public function getCachedAsyncGameSessionDbConnection(
        LoopInterface $loop,
        int $gameSessionId,
        bool $cacheRefresh = false
    ): DriftConnection {
        return $this->getCachedAsyncDbConnection(
            $loop,
            $this->getGameSessionDbName($gameSessionId),
            $this->getGameSessionConnectionOptions(),
            $cacheRefresh
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function createGameSessionConnection(int $gameSessionId): Connection
    {
        return $this->createDbConnection($this->getGameSessionDbName($gameSessionId));
    }

    public function createAsyncGameSessionConnection(LoopInterface $loop, int $gameSessionId): DriftConnection
    {
        return $this->createAsyncDbConnection(
            $loop,
            $this->getGameSessionDbName($gameSessionId),
            $this->getGameSessionConnectionOptions()
        );
    }
}
