<?php

namespace App\Domain\Services;

use App\Domain\API\v1\Config;
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

class ConnectionManager
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
            throw new Exception(
                'Instance is unavailable. It should be set by first constructor call, using Symfony services.'
            );
        }
        return self::$instance;
    }

    public function getConfig(): array
    {
        // if there will be multiple version of the api, e.g. v1/Config.php, v2/Config.php, we can just pick one since
        //   currently all will read from the same api_config.php file.
        // @todo: Config class should not be part of api versioning? Or, load the class with a dynamic class path?
        return Config::getInstance()->DatabaseConfig();
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
        $dbConfig = $this->getConfig();
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $dbConfig['host'],
            'port' => $dbConfig['port'] ?? '3306',
            'user' => $dbConfig['user'],
            'password' => $dbConfig['password'],
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
            'serverVersion' => '5.5.5-10.4.22'
        ]);
        $platform = $connection->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');
        return $connection;
    }

    public function createAsyncDbConnection(
        LoopInterface $loop,
        string $dbName,
        ?ConnectionOptions $options = null
    ): DriftConnection {
        $dbConfig = $this->getConfig();
        $mysqlPlatform = new MySqlPlatform();
        $mysqlDriver = new MysqlDriver($loop);
        $credentials = new Credentials(
            $dbConfig['host'],
            $dbConfig['port'] ?? '3306',
            $dbConfig['user'],
            $dbConfig['password'],
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

    private function getServerManagerDbName(): string
    {
        $dbConfig = $this->getConfig();
        return $dbConfig['database'];
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

    private function getGameSessionDbName(int $gameSessionId): string
    {
        $dbConfig = $this->getConfig();
        return $dbConfig['multisession_database_prefix'].$gameSessionId;
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
        return new ConnectionPoolOptions(20, 14400);
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
