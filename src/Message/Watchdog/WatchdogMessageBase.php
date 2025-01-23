<?php

namespace App\Message\Watchdog;

use App\Entity\Watchdog;

abstract class WatchdogMessageBase
{
    private int $gameSessionId;
    private ?Watchdog $watchdog = null;

    private int $month;

    public function getGameSessionId(): int
    {
        return $this->gameSessionId;
    }

    public function setGameSessionId(int $gameSessionId): static
    {
        $this->gameSessionId = $gameSessionId;
        return $this;
    }

    public function getWatchdog(): ?Watchdog
    {
        return $this->watchdog;
    }

    public function setWatchdog(?Watchdog $watchdog): static
    {
        $this->watchdog = $watchdog;
        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;
        return $this;
    }
}
