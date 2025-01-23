<?php

namespace App\Domain\API\v1;

use App\Domain\Common\EntityEnums\EventLogSeverity;
use App\Entity\EventLog;
use App\Domain\Services\SymfonyToLegacyHelper;
use DateTime;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\await;
use function React\Promise\resolve;

class Log extends Base
{
    const WARNING = "Warning";
    const ERROR = "Error";
    const FATAL = "Fatal";

    public const LOG_ERROR = (1 << 0);
    public const LOG_WARNING = (1 << 1);
    public const LOG_INFO = (1 << 2);
    public const LOG_DEBUG = (1 << 3);

    private static int $logFilter = ~0;

    /**
     * called from SEL
     * @apiGroup Log
     * @apiDescription Posts an 'error' event in the server log.
     * @throws Exception
     * @api {POST} /Log/Event Event
     * @apiParam {string} source Source component of the error. Examples: Server, MEL, CEL, SEL etc.
     * @apiParam {string} severity Severity of the errror ["Warning"|"Error"|"Fatal"]
     * @apiParam {string} message Debugging information associated with this event
     * @apiParam {string} stack_trace Debug stacktrace where the error occured. Optional.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Event(string $source, string $severity, string $message, string $stack_trace = ""): void
    {
        $eventLog = new EventLog();
        $eventLog->setSource($source);
        $eventLog->setSeverity(EventLogSeverity::from($severity));
        $eventLog->setMessage($message);
        $eventLog->setStackTrace($stack_trace);
        $this->postEvent($eventLog);
    }

    /**
     * @throws Exception
     */
    public function postEvent(EventLog $eventLog): ?PromiseInterface
    {
        if (null === $message = $eventLog->getMessage()) {
            resolve(); // do not do anything.
        }
        $eventLog->setTime(new DateTime());
        $data = [
            'event_log_time' => $eventLog->getTime()->format('Y-m-d H:i:s'),
            'event_log_source' => $eventLog->getSource() ?? 'source not set',
            'event_log_severity' => $eventLog->getSeverity(),
            'event_log_message' => $eventLog->getMessage()
        ];
        if ($eventLog->getStackTrace() !== null) {
            $data['event_log_stack_trace'] = $eventLog->getStackTrace();
        }
        if ($eventLog->getReferenceObject() !== null) {
            $data['event_log_reference_object'] = $eventLog->getReferenceObject();
        }
        if ($eventLog->getReferenceId() !== null) {
            $data['event_log_reference_id'] = $eventLog->getReferenceId();
        }
        $deferred = new Deferred();
        $this->getAsyncDatabase()->insert('event_log', $data)
            ->done(
                function () use ($deferred) {
                    $deferred->resolve(); // we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @throws Exception
     */
    public function serverEvent(string $source, string $severity, string $message): void
    {
        $eventLog = new EventLog();
        $eventLog->setSource($source);
        $eventLog->setSeverity(EventLogSeverity::from($severity));
        $eventLog->setMessage($message);
        $e = new Exception();
        $eventLog->setStackTrace($e->getTraceAsString());
        $this->postEvent($eventLog);
    }

    /**
     * @throws Exception
     */
    public static function getRecreateLogPath(): string
    {
        $rootPath = SymfonyToLegacyHelper::getInstance()->getProjectDir();
        $logPrefix = 'log_session_';
        $sessionId = $_REQUEST['session'];

        $log_dir = $rootPath.'/ServerManager/log';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }

        return $log_dir.'/'. $logPrefix . $sessionId . '.log';
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function SetupFileLogger(string $logPath): void
    {
        file_put_contents($logPath, "");
        ob_start(
            /**
             * @throws Exception
             */
            function (string $message, int $phase) {
                return self::recreateLoggingHandler($message, $phase);
            },
            16
        );
        ob_implicit_flush(true);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ClearFileLogger(): void
    {
        ob_end_flush();
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function LogError(string $message): void
    {
        self::FormatAndPrintToLog($message, self::LOG_ERROR);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function LogWarning(string $message): void
    {
        self::FormatAndPrintToLog($message, self::LOG_WARNING);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function LogInfo(string $message): void
    {
        self::FormatAndPrintToLog($message, self::LOG_INFO);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function LogDebug(string $message): void
    {
        self::FormatAndPrintToLog($message, self::LOG_DEBUG);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function FormatAndPrintToLog(string $message, int $logLevel): void
    {
        if ((self::$logFilter & $logLevel) == 0) {
            return;
        }

        $dateNow = '[' . date("Y-m-d H:i:s") . ']';
        if (($logLevel & self::LOG_ERROR) == self::LOG_ERROR) {
            $logCategory = "ERROR";
        } elseif (($logLevel & self::LOG_WARNING) == self::LOG_WARNING) {
            $logCategory = "WARN";
        } elseif (($logLevel & self::LOG_INFO) == self::LOG_INFO) {
            $logCategory = "INFO";
        } elseif (($logLevel & self::LOG_DEBUG) == self::LOG_DEBUG) {
            $logCategory = "DEBUG";
        } else {
            $logCategory = "UNKNOWN";
        }

        print($dateNow . " [ ". $logCategory . ' ] - ' . $message);
    }

    /**
     * @throws Exception
     */
    private static function recreateLoggingHandler(string $message, int $phase): string
    {
        file_put_contents(self::getRecreateLogPath(), $message . PHP_EOL, FILE_APPEND);
        return ""; //Swallow all logging after this has been written to the log file.
    }
}
