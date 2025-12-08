<?php

namespace App\MessageHandler\GameList;

use Psr\Log\LoggerInterface;

class SessionLogHandler
{
    private ?int $gameSessionId = null;

    public function __construct(
        protected readonly LoggerInterface $gameSessionLogger
    ) {
    }

    public function setGameSessionId(int $gameSessionId): void
    {
        $this->gameSessionId = $gameSessionId;
    }

    public function log(string $level, string $message, array $contextVars = []): void
    {
        if (null === $this->gameSessionId) {
            throw new \LogicException('Game session id not set');
        }
        $contextVars['gameSession'] = $this->gameSessionId;
        $this->gameSessionLogger->$level($message, $contextVars);
    }

    public function info(string $message, array $contextVars = []): void
    {
        $this->log('info', $message, $contextVars);
    }

    public function debug(string $message, array $contextVars = []): void
    {
        $this->log('debug', $message, $contextVars);
    }

    public function notice(string $message, array $contextVars = []): void
    {
        $this->log('notice', $message, $contextVars);
    }

    public function warning(string $message, array $contextVars = []): void
    {
        $this->log('warning', $message, $contextVars);
    }

    public function error(string $message, array $contextVars = []): void
    {
        $this->log('error', $message, $contextVars);
    }
}
