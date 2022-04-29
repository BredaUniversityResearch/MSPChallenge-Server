<?php

namespace App\Domain\WsServer;

use App\Domain\Event\NameAwareEvent;

interface WsServerEventDispatcherInterface
{
    const EVENT_ON_CLIENT_CONNECTED = 'EVENT_ON_CLIENT_CONNECTED';
    const EVENT_ON_CLIENT_DISCONNECTED = 'EVENT_ON_CLIENT_DISCONNECTED';
    const EVENT_ON_CLIENT_ERROR = 'EVENT_ON_CLIENT_ERROR';
    const EVENT_ON_CLIENT_MESSAGE_RECEIVED = 'EVENT_ON_CLIENT_MESSAGE_RECEIVED';
    const EVENT_ON_CLIENT_MESSAGE_SENT = 'EVENT_ON_CLIENT_MESSAGE_SENT';
    const EVENT_ON_STATS_UPDATE = 'EVENT_ON_STATS_UPDATE';

    /**
     * @param object|NameAwareEvent $event
     * @param string|null $eventName
     * @return WsServerEventDispatcherInterface
     */
    public function dispatch(object $event, ?string $eventName = null): object;
}
