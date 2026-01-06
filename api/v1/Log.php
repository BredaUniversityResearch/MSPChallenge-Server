<?php

namespace App\Domain\API\v1;

use App\Domain\Common\EntityEnums\EventLogSeverity;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\SessionAPI\EventLog;
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
        if (null === $eventLog->getMessage()) {
            return resolve(null); // do not do anything.
        }
        $eventLog->setTime(new DateTime());
        $data = [
            'event_log_time' => $eventLog->getTime()->format('Y-m-d H:i:s'),
            'event_log_source' => $eventLog->getSource() ?? 'source not set',
            'event_log_severity' => $eventLog->getSeverity()->value,
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
            ->then(
                function () use ($deferred) {
                    $deferred->resolve(null); // we do not care about the result
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
}
