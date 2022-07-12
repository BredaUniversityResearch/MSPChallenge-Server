<?php

namespace App\Domain\Event;

use Symfony\Component\EventDispatcher\GenericEvent;

class NameAwareEvent extends GenericEvent implements NameAwareEventInterface
{
    private string $eventName;

    public function __construct(string $eventName, $subject = null, array $arguments = [])
    {
        $this->eventName = $eventName;
        parent::__construct($subject, $arguments);
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
