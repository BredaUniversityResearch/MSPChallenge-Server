<?php

namespace App\Doctrine;

use App\Domain\Common\DatabaseDefaults;
use App\Domain\Services\ProcessNameDetector;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\ParameterType;

final class DbalConnectionTracker
{
    public static function trackDriverConnection(
        DriverConnection $connection,
        ?string $dbName = null,
        ?string $connectionName = null,
    ): void {
        $trackingEnabled = filter_var(
            $_ENV['DATABASE_CONNECTION_TRACKING_ENABLED'] ?? '1',
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
        if (!$trackingEnabled) {
            return;
        }

        $processName = ProcessNameDetector::getProcessName();
        $trackingDebugEnabled = filter_var(
            $_ENV['DATABASE_CONNECTION_TRACKING_DEBUG_INPUTS'] ?? '0',
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? false;
        if ($trackingDebugEnabled) {
            $processName = ProcessNameDetector::buildDebugProcessName();
        }
        if (!$processName) {
            return;
        }

        $dbName ??= self::resolveDatabaseName($connectionName);
        $processName = substr($processName, 0, ProcessNameDetector::PROCESS_NAME_MAX_LENGTH);

        try {
            $sql = <<<'SQL'
INSERT INTO `msp_tracker`.`connection`
(connection_id, `user`, process_name, db_name)
VALUES (CONNECTION_ID(), USER(), ?, ?)
ON DUPLICATE KEY UPDATE
`user` = USER(), process_name = VALUES(process_name), db_name = VALUES(db_name),
last_heartbeat = NOW();
SQL;

            $statement = $connection->prepare($sql);
            $statement->bindValue(1, $processName, ParameterType::STRING);
            $statement->bindValue(2, (string) $dbName, ParameterType::STRING);
            $statement->execute();
        } catch (\Throwable $e) {
            // Never fail connection setup because of tracker diagnostics.
        }
    }

    private static function resolveDatabaseName(?string $connectionName): string
    {
        if ($connectionName !== null && $connectionName !== '' && $connectionName !== 'default') {
            return $connectionName;
        }
        return $_ENV['DBNAME_DEFAULT'] ?? $_ENV['DBNAME_SERVER_MANAGER'] ??
            DatabaseDefaults::DEFAULT_DBNAME_SERVER_MANAGER;
    }
}
