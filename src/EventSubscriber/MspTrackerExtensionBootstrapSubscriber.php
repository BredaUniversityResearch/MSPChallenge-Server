<?php

namespace App\EventSubscriber;

use App\Domain\Services\ProcessNameDetector;
use App\Message\ConnectionTracking\LowLevelConnectionTrackedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;

final class MspTrackerExtensionBootstrapSubscriber implements EventSubscriberInterface
{
    /**
     * The closure is built once per service instance and reused across registrations.
     * We intentionally do NOT keep a "$registered" guard here because in
     * ZTS / FrankenPHP worker mode PHP_RSHUTDOWN clears the extension's
     * per-request globals (has_callback → 0) while this long-lived PHP service
     * object survives across requests.  Re-calling msp_tracker_register_connection_callback()
     * on every request/command is cheap (it just overwrites the stored zval)
     * and is the only safe way to re-arm the tracker after every RSHUTDOWN.
     */
    private ?\Closure $trackingCallback = null;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // PHP_INT_MAX ensures we run before any other listener that might
            // open a Doctrine/PDO connection at the same event.
            KernelEvents::REQUEST => ['onKernelRequest', PHP_INT_MAX],
            ConsoleEvents::COMMAND => ['onConsoleCommand', PHP_INT_MAX],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $this->registerCallbackIfAvailable();
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->registerCallbackIfAvailable('console:' . ($event->getCommand()?->getName() ?? 'unknown'));
    }

    public function registerCallbackIfAvailable(string $context = 'unknown'): void
    {
        if (!function_exists('msp_tracker_register_connection_callback')) {
            return;
        }

        if ($this->trackingCallback === null) {
            $this->trackingCallback = function (array $payload): void {
                try {
                    $processName = ProcessNameDetector::getProcessName('php_unknown') ?? 'php_unknown';
                    $payload['process_name'] = $payload['process_name'] ?? $processName;
                    $payload['call_stack'] = $payload['call_stack'] ?? $this->resolveCallStack();

                    $this->messageBus->dispatch(new LowLevelConnectionTrackedMessage($payload));
                } catch (\Throwable $e) {
                    // Never let tracking errors crash the original PDO::__construct caller.
                    $this->logger->warning('msp_tracker: failed to dispatch connection tracking message', [
                        'error' => $e->getMessage(),
                        'payload_event' => $payload['event'] ?? 'unknown',
                    ]);
                }
            };
        }

        $registered = msp_tracker_register_connection_callback($this->trackingCallback);
        $this->logger->debug('msp_tracker: callback registration attempt', [
            'context' => $context,
            'result' => $registered ? 'ok' : 'failed',
        ]);
    }


    /**
     * Returns a compact, transport-safe full stack trace snapshot.
     * Each frame is a single string to keep messenger serialization simple.
     *
     * @return list<string>
     */
    private function resolveCallStack(): array
    {
        $stack = [];
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 64) as $index => $frame) {
            $class = is_string($frame['class'] ?? null) ? $frame['class'] : null;
            $type = is_string($frame['type'] ?? null) ? $frame['type'] : null;
            $function = $frame['function'];
            $file = is_string($frame['file'] ?? null) ? $frame['file'] : null;
            $line = is_int($frame['line'] ?? null) ? $frame['line'] : null;

            $call = trim(($class ?? '') . ($type ?? '') . $function);
            $location = $file !== null && $line !== null ? $file . ':' . $line : 'internal';
            $stack[] = '#' . $index . ' ' . $location . ' ' . $call;
        }

        return $stack;
    }
}
