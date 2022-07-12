<?php

namespace App\Domain\Event;

interface NameAwareEventInterface
{
    public function getEventName(): string;
}
