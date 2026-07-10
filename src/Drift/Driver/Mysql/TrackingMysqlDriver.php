<?php

namespace App\Drift\Driver\Mysql;

use App\Domain\Common\DatabaseDefaults;
use App\Domain\Services\ProcessNameDetector;
use Drift\DBAL\Credentials;
use Drift\DBAL\Driver\Mysql\MysqlDriver;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

/**
 * Async Drift connections do not expose a PDO object, so the extension cannot
 * query MySQL CONNECTION_ID() for them. Keep this thin app-side bridge for the
 * async driver only; all PDO/DBAL tracking is extension-driven.
 */
class TrackingMysqlDriver extends MysqlDriver
{
    private bool $trackingInitialized = false;
    private bool $trackingInProgress = false;
    private ?string $connectionDbName = null;

    public function connect(Credentials $credentials, array $options = [])
    {
        $this->connectionDbName = $credentials->getDbName();
        $this->trackingInitialized = false;
        $this->trackingInProgress = false;

        parent::connect($credentials, $options);

        // Kick off tracker insert at connect-time so idle connections are still visible.
        $this->initializeConnectionTracking();
    }

    public function query(string $sql, array $parameters): PromiseInterface
    {
        if ($this->trackingInitialized || $this->trackingInProgress) {
            return parent::query($sql, $parameters);
        }

        return $this->initializeConnectionTracking()->then(
            fn () => parent::query($sql, $parameters),
            fn () => parent::query($sql, $parameters)
        );
    }

    private function initializeConnectionTracking(): PromiseInterface
    {
        $trackingEnabled = filter_var(
            $_ENV['DATABASE_CONNECTION_TRACKING_ENABLED'] ?? '1',
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
        if (!$trackingEnabled) {
            $this->trackingInitialized = true;
            return resolve(true)->then(static fn () => null);
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
            $this->trackingInitialized = true;
            return resolve(true)->then(static fn () => null);
        }

        $this->trackingInProgress = true;

        $dbName = $this->connectionDbName
            ?? ($_ENV['DBNAME_DEFAULT'] ?? $_ENV['DBNAME_SERVER_MANAGER'] ??
                DatabaseDefaults::DEFAULT_DBNAME_SERVER_MANAGER);

        $processName = substr($processName, 0, ProcessNameDetector::PROCESS_NAME_MAX_LENGTH);

        $trackingSql = <<<'SQL'
INSERT INTO `msp_tracker`.`connection`
(connection_id, `user`, process_name, db_name, call_stack)
VALUES (CONNECTION_ID(), USER(), ?, ?, ?)
ON DUPLICATE KEY UPDATE
`user` = USER(), process_name = VALUES(process_name), db_name = VALUES(db_name),
call_stack = VALUES(call_stack),
last_heartbeat = NOW();
SQL;

        $callStackJson = $this->buildCallStackJson();

        return parent::query($trackingSql, [$processName, (string) $dbName, $callStackJson])->then(
            function () {
                $this->trackingInitialized = true;
                $this->trackingInProgress = false;
                return null;
            },
            function () {
                // Never fail Drift query flow because of tracker diagnostics.
                $this->trackingInitialized = true;
                $this->trackingInProgress = false;
                return null;
            }
        );
    }

    private function buildCallStackJson(): ?string
    {
        $stack = [];
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 32) as $index => $frame) {
            $class = is_string($frame['class'] ?? null) ? $frame['class'] : null;
            $type = is_string($frame['type'] ?? null) ? $frame['type'] : null;
            $function = $frame['function'];
            $file = is_string($frame['file'] ?? null) ? $frame['file'] : null;
            $line = is_int($frame['line'] ?? null) ? $frame['line'] : null;

            $call = trim(($class ?? '') . ($type ?? '') . $function);
            $location = $file !== null && $line !== null ? $file . ':' . $line : 'internal';
            $stack[] = '#' . $index . ' ' . $location . ' ' . $call;
        }

        $encoded = json_encode($stack, JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : null;
    }
}
