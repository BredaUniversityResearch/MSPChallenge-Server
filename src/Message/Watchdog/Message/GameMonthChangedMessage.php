<?php

namespace App\Message\Watchdog\Message;

class GameMonthChangedMessage extends WatchdogMessageBase
{
    private int $month;

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
