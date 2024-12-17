<?php

namespace App\Message\Watchdog;

use App\Entity\Watchdog;

abstract class WatchdogMessageBase
{
    private int $gameSessionId;
    private ?Watchdog $watchdog = null;

    public function getGameSessionId(): int
    {
        return $this->gameSessionId;
    }

    public function setGameSessionId(int $gameSessionId): self
    {
        $this->gameSessionId = $gameSessionId;
        return $this;
    }

    public function getWatchdog(): ?Watchdog
    {
        return $this->watchdog;
    }

    public function setWatchdog(?Watchdog $watchdog): self
    {
        $this->watchdog = $watchdog;
        return $this;
    }
}
