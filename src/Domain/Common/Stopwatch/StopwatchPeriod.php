<?php

namespace App\Domain\Common\Stopwatch;

/***
 * Represents a Period for an Event.
 *
 * copied and base from https://github.com/symfony/stopwatch/blob/7.3/StopwatchEvent.php
 */
readonly class StopwatchPeriod
{
    public function __construct(
        private int $id,
        private StopwatchEventMeasurement $start,
        private StopwatchEventMeasurement $end
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Gets the relative time of the start of the period in milliseconds.
     */
    public function getStartTime(): int|float
    {
        return $this->start->getTime();
    }

    /**
     * Gets the relative time of the end of the period in milliseconds.
     */
    public function getEndTime(): int|float
    {
        return $this->end->getTime();
    }

    /**
     * Gets the time spent in this period in milliseconds.
     */
    public function getDuration(): int|float
    {
        return $this->end->getTime() - $this->start->getTime();
    }

    /**
     * Gets the memory usage in bytes at the start of the period.
     */
    public function getStartMemory(): int
    {
        return $this->start->getMemory();
    }

    /**
     * Gets the memory usage in bytes at the end of the period.
     */
    public function getEndMemory(): int
    {
        return $this->end->getMemory();
    }

    /**
     * Gets the memory usage in bytes during this period.
     */
    public function getMemoryUsage(): int
    {
        $diff = $this->end->getMemory() - $this->start->getMemory();
        return max($diff, 0);
    }

    public function __toString(): string
    {
        return \sprintf('%.2F MiB - %d ms', $this->getMemoryUsage() / 1024 / 1024, $this->getDuration());
    }
}
