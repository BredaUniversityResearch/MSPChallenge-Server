<?php

namespace App\MessageHandler\ConnectionTracking;

use App\Domain\Common\DatabaseDefaults;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\ProcessNameDetector;
use App\Message\ConnectionTracking\LowLevelConnectionTrackedMessage;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

final class LowLevelConnectionTrackedMessageHandler
{
    private bool $handling = false;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LowLevelConnectionTrackedMessage $message): void
    {
        if ($this->handling || !$this->isTrackingEnabled()) {
            return;
        }

        $payload = $message->getPayload();

        $connectionId = $message->getConnectionId();
        if ($connectionId <= 0) {
            return;
        }

        $this->handling = true;
        try {
            if (function_exists('msp_tracker_set_enabled')) {
                msp_tracker_set_enabled(false);
            }

            $processName = $message->getProcessName()
                ?? ProcessNameDetector::getProcessName('php_unknown')
                ?? 'php_unknown';
            $processName = substr($processName, 0, ProcessNameDetector::PROCESS_NAME_MAX_LENGTH);

            $dbName = $message->getDatabaseName()
                ?? ($_ENV['DBNAME_DEFAULT'] ?? $_ENV['DBNAME_SERVER_MANAGER'] ??
                    DatabaseDefaults::DEFAULT_DBNAME_SERVER_MANAGER);
            $user = $message->getUser() ?? 'unknown';
            $callStack = is_array($payload['call_stack'] ?? null) ? $payload['call_stack'] : null;
            $callStackJson = $callStack !== null ? json_encode($callStack, JSON_UNESCAPED_SLASHES) : null;
            if (!is_string($callStackJson)) {
                $callStackJson = null;
            }

            $connection = $this->connectionManager->createServerManagerConnection();
            try {
                $sql = <<<'SQL'
INSERT INTO `msp_tracker`.`connection`
(connection_id, `user`, process_name, db_name, call_stack)
VALUES (?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
`user` = VALUES(`user`), process_name = VALUES(process_name), db_name = VALUES(db_name),
call_stack = VALUES(call_stack),
last_heartbeat = NOW();
SQL;
                $connection->executeStatement($sql, [
                    $connectionId,
                    $user,
                    $processName,
                    (string) $dbName,
                    $callStackJson,
                ], [
                    ParameterType::INTEGER,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::STRING,
                ]);
            } finally {
                $connection->close();
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to handle low-level DB connection tracking message', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            if (function_exists('msp_tracker_set_enabled')) {
                msp_tracker_set_enabled(true);
            }
            $this->handling = false;
        }
    }

    private function isTrackingEnabled(): bool
    {
        return filter_var(
            $_ENV['DATABASE_CONNECTION_TRACKING_ENABLED'] ?? '1',
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
    }
}
