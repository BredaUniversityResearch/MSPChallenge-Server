<?php

namespace App\Message\Watchdog\Message;

abstract class WatchdogMessageBase
{
    private int $gameSessionId;
    private int $watchdogId;

    public function getGameSessionId(): int
    {
        return $this->gameSessionId;
    }

    public function setGameSessionId(int $gameSessionId): static
    {
        $this->gameSessionId = $gameSessionId;
        return $this;
    }

    public function getWatchdogId(): int
    {
        return $this->watchdogId;
    }

    public function setWatchdogId(int $watchdogId): static
    {
        $this->watchdogId = $watchdogId;
        return $this;
    }
}
