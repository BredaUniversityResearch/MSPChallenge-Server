<?php

namespace App\MessageHandler\Retry;

use App\Message\Docker\CreateImmersiveSessionConnectionMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;

class DockerMessageRetryStrategy implements RetryStrategyInterface
{
    public const MAX_RETRIES = 5;

    private RetryStrategyInterface $defaultRetryStrategy;

    public function __construct(
        int $maxRetries = self::MAX_RETRIES, // 2, 4, 8, 16, 32 . summing up to 62 seconds
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
            CreateImmersiveSessionConnectionMessage::class =>
                $this->defaultRetryStrategy->isRetryable($message, $throwable),
            default => false,
        };
    }

    public function getWaitingTime(Envelope $message, \Throwable $throwable = null): int
    {
        // Delegate waiting time calculation to MultiplierRetryStrategy
        return $this->defaultRetryStrategy->getWaitingTime($message, $throwable);
    }
}
