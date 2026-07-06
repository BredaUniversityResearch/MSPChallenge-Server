<?php

namespace App\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Middleware that updates DB_PROCESS_NAME based on the message handler being executed.
 * This allows connection tracking to identify which async message handler is processing.
 */
class ProcessNameDetectorMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $previousProcessName = $_ENV['DB_PROCESS_NAME'] ?? null;
        $handlerName = $this->extractHandlerName($message::class);

        // Set dynamic process name based on message class
        $_ENV['DB_PROCESS_NAME'] = $handlerName;

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            // Restore previous process name after handler completes
            if ($previousProcessName !== null) {
                $_ENV['DB_PROCESS_NAME'] = $previousProcessName;
            } else {
                unset($_ENV['DB_PROCESS_NAME']);
            }
        }
    }

    /**
     * Extract a human-readable handler name from the message class.
     * Examples:
     *   App\Message\GameList\GameListCreationMessage -> game-list-creation
     *   App\Message\GameSave\GameSaveLoadMessage -> game-save-load
     *   App\Message\Watchdog\Message\WatchdogMessageBase -> watchdog-message
     */
    private function extractHandlerName(string $messageClass): string
    {
        // Get the short class name and remove 'Message' suffix
        $shortName = substr(strrchr($messageClass, '\\'), 1);

        // Remove 'Message' suffix
        $withoutSuffix = preg_replace('/Message$/', '', $shortName);

        // Convert from CamelCase to kebab-case
        $kebabCase = strtolower(preg_replace('/(?<!^)(?=[A-Z])/', '-', $withoutSuffix));

        // Prefix with 'messenger-' to identify it's a messenger handler
        return 'messenger-' . $kebabCase;
    }
}
