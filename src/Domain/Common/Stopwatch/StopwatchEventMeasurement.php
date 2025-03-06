<?php

namespace App\Domain\Common\Stopwatch;

readonly class StopwatchEventMeasurement
{
    private int|float $time;
    private int $memory;

    public function __construct(
        int|float $time,
        int $memory,
        bool $morePrecision = false
    ) {
        $this->time = $morePrecision ? (float) $time : (int) $time;
        $this->memory = $memory;
    }

    public function getTime(): int|float
    {
        return $this->time;
    }

    public function getMemory(): int
    {
        return $this->memory;
    }
}
