<?php

namespace App\Domain\Common\Stopwatch;

/***
 * Represents an Event managed by Stopwatch.
 *
 * copied and base from https://github.com/symfony/stopwatch/blob/7.3/StopwatchEvent.php
 */
class StopwatchEvent
{
    /**
     * @var StopwatchPeriod[]
     */
    private array $periods = [];

    private float $origin;
    private string $category;

    /**
     * @var StopwatchEventMeasurement[]
     */
    private array $started = [];

    private string $name;

    private int $nextId = 1;

    /**
     * @param float       $origin        The origin time in milliseconds
     * @param string|null $category      The event category or null to use the default
     * @param bool        $morePrecision If true, time is stored as float to keep the original microsecond precision
     * @param string|null $name          The event name or null to define the name as default
     *
     * @throws \InvalidArgumentException When the raw time is not valid
     */
    public function __construct(
        float $origin,
        ?string $category = null,
        private bool $morePrecision = false,
        ?string $name = null,
    ) {
        $this->origin = $this->formatTime($origin);
        $this->category = \is_string($category) ? $category : 'default';
        $this->name = $name ?? 'default';
    }

    /**
     * Gets the category.
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Gets the origin in milliseconds.
     */
    public function getOrigin(): float
    {
        return $this->origin;
    }

    /**
     * Starts a new event period.
     *
     * @return $this
     */
    public function start(): static
    {
        $this->started[] = $this->getNow();

        return $this;
    }

    /**
     * Stops the last started event period.
     *
     * @return $this
     *
     * @throws \LogicException When stop() is called without a matching call to start()
     */
    public function stop(): static
    {
        if (!\count($this->started)) {
            throw new \LogicException('stop() called but start() has not been called before.');
        }

        $this->periods[] = new StopwatchPeriod($this->nextId, array_pop($this->started), $this->getNow());
        // limit the number of periods to avoid memory issues
        $this->periods = array_slice($this->periods, -1000);
        $this->nextId++;

        return $this;
    }

    /**
     * Checks if the event was started.
     */
    public function isStarted(): bool
    {
        return (bool) $this->started;
    }

    /**
     * Stops the current period and then starts a new one.
     *
     * @return $this
     */
    public function lap(): static
    {
        return $this->stop()->start();
    }

    /**
     * Stops all non already stopped periods.
     */
    public function ensureStopped(): void
    {
        while (\count($this->started)) {
            $this->stop();
        }
    }

    /**
     * Gets all event periods.
     *
     * @return StopwatchPeriod[]
     */
    public function getPeriods(): array
    {
        return $this->periods;
    }

    /***
     * Gets the periods with memory usage difference, maintaining the original keys.
     */
    public function getPeriodsWithMemoryIncrease(): array
    {
        $periods = $this->getPeriods();
        $periodsWithMemoryUsageDifference = [];
        $previousMemory = null;
        foreach ($periods as $key => $period) {
            $currentMemory = $period->getMemoryUsage();
            if ($previousMemory === null ||
                $currentMemory - $previousMemory > 1024
            ) { // 1KB of difference
                $periodsWithMemoryUsageDifference[$key] = $period;
                $previousMemory = $currentMemory;
            }
        }

        return $periodsWithMemoryUsageDifference;
    }

    /**
     * Gets the last event period.
     */
    public function getLastPeriod(): ?StopwatchPeriod
    {
        if ([] === $this->periods) {
            return null;
        }

        return $this->periods[array_key_last($this->periods)];
    }

    /**
     * Gets the relative time of the start of the first period in milliseconds.
     */
    public function getStartTime(): int|float
    {
        if (isset($this->periods[0])) {
            return $this->periods[0]->getStartTime();
        }

        if ($this->started) {
            return $this->started[0]->getTime();
        }

        return 0;
    }

    /**
     * Gets the relative time of the end of the last period in milliseconds.
     */
    public function getEndTime(): int|float
    {
        $count = \count($this->periods);

        return $count ? $this->periods[$count - 1]->getEndTime() : 0;
    }


    /**
     * Gets the memory of the start of the first period in bytes.
     */
    public function getStartMemory(): int
    {
        if (isset($this->periods[0])) {
            return $this->periods[0]->getStartMemory();
        }

        if ($this->started) {
            return $this->started[0]->getMemory();
        }

        return 0;
    }

    /**
     * Gets the memory of the end of the last period in bytes.
     */
    public function getEndMemory(): int
    {
        $count = \count($this->periods);

        return $count ? $this->periods[$count - 1]->getEndMemory() : 0;
    }

    /**
     * Gets the duration of the events in milliseconds (including all periods).
     */
    public function getDuration(): int|float
    {
        $periods = $this->periods;
        $left = \count($this->started);

        for ($i = $left - 1; $i >= 0; --$i) {
            $periods[] = new StopwatchPeriod($i, $this->started[$i], $this->getNow());
        }

        $total = 0;
        foreach ($periods as $period) {
            $total += $period->getDuration();
        }

        return $total;
    }

    /**
     * Gets the max memory usage during periods
     */
    public function getPeakMemoryUsage(): int
    {
        $memory = 0;
        foreach ($this->periods as $period) {
            if ($period->getMemoryUsage() > $memory) {
                $memory = $period->getMemoryUsage();
            }
        }

        return $memory;
    }

    /**
     * Return the current time relative to origin in milliseconds.
     */
    protected function getNow(): StopwatchEventMeasurement
    {
        return new StopwatchEventMeasurement(
            microtime(true) * 1000 - $this->origin,
            memory_get_usage(),
            $this->morePrecision
        );
    }

    /**
     * Formats a time.
     *
     * @throws \InvalidArgumentException When the raw time is not valid
     */
    private function formatTime(float $time): float
    {
        return round($time, 1);
    }

    /**
     * Gets the event name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return \sprintf(
            '%s/%s: %.2F MiB - %d ms',
            $this->getCategory(),
            $this->getName(),
            $this->getPeakMemoryUsage() / 1024 / 1024,
            $this->getDuration()
        );
    }
}
