<?php

namespace App\Domain\Helper;

use App\Domain\API\v1\Config;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Drift\DBAL\Connection;
use Drift\DBAL\ConnectionPool;
use Drift\DBAL\ConnectionPoolOptions;
use Drift\DBAL\Credentials;
use Drift\DBAL\Driver\Mysql\MysqlDriver;
use Drift\DBAL\SingleConnection;
use React\EventLoop\LoopInterface;

class AsyncDatabase
{
    public static function getConfig(): array
    {
        // if there will be multiple version of the api, e.g. v1/Config.php, v2/Config.php, we can just pick one since
        //   currently all will read from the same api_config.php file.
        // @todo: Config class should not be part of api versioning? Or, load the class with a dynamic class path?
        return Config::getInstance()->DatabaseConfig();
    }

    public static function createServerManagerConnection(LoopInterface $loop): Connection
    {
        $dbConfig = self::getConfig();
        $mysqlPlatform = new MySqlPlatform();
        $mysqlDriver = new MysqlDriver($loop);
        $credentials = new Credentials(
            $dbConfig['host'],
            $dbConfig['port'] ?? '3306',
            $dbConfig['user'],
            $dbConfig['password'],
            $dbConfig['database']
        );
        return SingleConnection::createConnected($mysqlDriver, $credentials, $mysqlPlatform);
    }

    public static function createGameSessionConnection(LoopInterface $loop, int $gameSessionId): Connection
    {
        $dbConfig = self::getConfig();
        $mysqlPlatform = new MySqlPlatform();
        $mysqlDriver = new MysqlDriver($loop);
        $credentials = new Credentials(
            $dbConfig['host'],
            $dbConfig['port'] ?? '3306',
            $dbConfig['user'],
            $dbConfig['password'],
            $dbConfig['multisession_database_prefix'].$gameSessionId
        );

        return ConnectionPool::createConnected(
            $mysqlDriver,
            $credentials,
            $mysqlPlatform,
            // Set $keepAliveIntervalSec to 14400 = 4 hours
            // This will do a SELECT 1 query every 4 hours to prevent the "wait_timeout" of mysql (Default is 8 hours).
            // If the wait timeout would go off, the database connection will be broken, and the error
            //   "2006 MySQL server has gone away" will appear.
            new ConnectionPoolOptions(20, 14400)
        );
    }
}
