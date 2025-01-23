<?php

namespace App\MessageHandler\GameList;

use Psr\Log\LoggerInterface;

abstract class SessionLogHandlerBase
{
    private ?int $gameSessionId;

    public function __construct(
        protected readonly LoggerInterface $gameSessionLogger
    ) {
    }

    protected function setGameSessionId(int $gameSessionId): void
    {
        $this->gameSessionId = $gameSessionId;
    }

    private function log(string $level, string $message, array $contextVars = []): void
    {
        if (null === $this->gameSessionId) {
            throw new \LogicException('Game session id not set');
        }
        $contextVars['gameSession'] = $this->gameSessionId;
        $this->gameSessionLogger->$level($message, $contextVars);
    }

    protected function info(string $message, array $contextVars = []): void
    {
        $this->log('info', $message, $contextVars);
    }

    protected function debug(string $message, array $contextVars = []): void
    {
        $this->log('debug', $message, $contextVars);
    }

    protected function notice(string $message, array $contextVars = []): void
    {
        $this->log('notice', $message, $contextVars);
    }

    protected function warning(string $message, array $contextVars = []): void
    {
        $this->log('warning', $message, $contextVars);
    }

    protected function error(string $message, array $contextVars = []): void
    {
        $this->log('error', $message, $contextVars);
    }
}
