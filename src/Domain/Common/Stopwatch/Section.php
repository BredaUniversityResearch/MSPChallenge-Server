<?php

namespace App\Domain\Common\Stopwatch;

/***
 * Stopwatch section.
 *
 * copied and base from https://github.com/symfony/stopwatch/blob/7.3/Section.php
 */
class Section
{
    /**
     * @var StopwatchEvent[]
     */
    private array $events = [];

    private ?string $id = null;

    /**
     * @var Section[]
     */
    private array $children = [];

    /**
     * @param float|null $origin Set the origin of the events in this section, use null to set their origin to their
     *   start time
     * @param bool $morePrecision If true, time is stored as float to keep the original microsecond precision
     */
    public function __construct(
        private ?float $origin = null,
        private bool $morePrecision = false,
    ) {
    }

    /**
     * Returns the child section.
     */
    public function get(string $id): ?self
    {
        foreach ($this->children as $child) {
            if ($id === $child->getId()) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Creates or re-opens a child section.
     *
     * @param string|null $id Null to create a new section, the identifier to re-open an existing one
     */
    public function open(?string $id): self
    {
        if (null === $id || null === $session = $this->get($id)) {
            $session = $this->children[] = new self(microtime(true) * 1000, $this->morePrecision);
        }

        return $session;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Sets the session identifier.
     *
     * @return $this
     */
    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Starts an event.
     */
    public function startEvent(string $name, ?string $category): StopwatchEvent
    {
        if (!isset($this->events[$name])) {
            $this->events[$name] = new StopwatchEvent(
                $this->origin ?: microtime(true) * 1000,
                $category,
                $this->morePrecision,
                $name
            );
        }

        return $this->events[$name]->start();
    }

    /**
     * Checks if the event was started.
     */
    public function isEventStarted(string $name): bool
    {
        return isset($this->events[$name]) && $this->events[$name]->isStarted();
    }

    /**
     * Stops an event.
     *
     * @throws \LogicException When the event has not been started
     */
    public function stopEvent(string $name): StopwatchEvent
    {
        if (!isset($this->events[$name])) {
            throw new \LogicException(\sprintf('Event "%s" is not started.', $name));
        }

        return $this->events[$name]->stop();
    }

    /**
     * Stops then restarts an event.
     *
     * @throws \LogicException When the event has not been started
     */
    public function lap(string $name): StopwatchEvent
    {
        return $this->stopEvent($name)->start();
    }

    /**
     * Returns a specific event by name.
     *
     * @throws \LogicException When the event is not known
     */
    public function getEvent(string $name): StopwatchEvent
    {
        if (!isset($this->events[$name])) {
            throw new \LogicException(\sprintf('Event "%s" is not known.', $name));
        }

        return $this->events[$name];
    }

    /**
     * Returns the events from this section.
     *
     * @return StopwatchEvent[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
