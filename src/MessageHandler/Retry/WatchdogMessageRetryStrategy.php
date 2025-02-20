<?php

namespace App\MessageHandler\Retry;

use App\Message\Watchdog\Message\WatchdogPingMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;

class WatchdogMessageRetryStrategy implements RetryStrategyInterface
{
    private RetryStrategyInterface $defaultRetryStrategy;

    public function __construct(
        int $maxRetries = 5, // 2, 4, 8, 16, 32 . summing up to 62 seconds
        int $initialDelay = 2000,
        float $multiplier = 2.0,
        int $maxDelay = 70000
    ) {
        // Initialize Symfony's MultiplierRetryStrategy with our own parameters
        $this->defaultRetryStrategy = new MultiplierRetryStrategy($maxRetries, $initialDelay, $multiplier, $maxDelay);
    }

    public function isRetryable(Envelope $message, \Throwable $throwable = null): bool
    {
        return match ($message->getMessage()::class) {
            WatchdogPingMessage::class => false,
            default => $this->defaultRetryStrategy->isRetryable($message, $throwable),
        };
    }

    public function getWaitingTime(Envelope $message, \Throwable $throwable = null): int
    {
        // Delegate waiting time calculation to MultiplierRetryStrategy
        return $this->defaultRetryStrategy->getWaitingTime($message, $throwable);
    }
}
