<?php

namespace App\Domain\WsServer\Console;

use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\WsServerEventDispatcherInterface;

class DefaultView extends ViewBase
{
    public function getName(): string
    {
        return 'default';
    }

    protected function postponeRender(NameAwareEvent $event): bool
    {
        // this it not an event that should be rendered.
        return $event->getEventName() == WsServerEventDispatcherInterface::EVENT_ON_STATS_UPDATE;
    }

    protected function render(NameAwareEvent $event): void
    {
        $this->outputEvent($event);
    }

    protected function process(NameAwareEvent $event): void
    {
        return; // nothing to do.
    }
}
