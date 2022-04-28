<?php

namespace App\Domain\WsServer;

use App\Domain\Event\NameAwareEvent;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;

class WsServerConnection
{
    private ConnectionInterface $conn;
    public RequestInterface $httpRequest;
    public int $resourceId;

    private ?WsServerEventDispatcherInterface $eventDispatcher = null;

    public function __construct(ConnectionInterface $conn)
    {
        $this->conn = $conn;
        /** @noinspection PhpUndefinedFieldInspection */
        $this->httpRequest = $conn->httpRequest;
        /** @noinspection PhpUndefinedFieldInspection */
        $this->resourceId = $conn->resourceId;
    }

    public function getEventDispatcher(): ?WsServerEventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function setEventDispatcher(WsServerEventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function send($data): ConnectionInterface
    {
        if (null !== $this->eventDispatcher) {
            $this->eventDispatcher->dispatch(
                new NameAwareEvent(
                    WsServerEventDispatcherInterface::EVENT_ON_CLIENT_MESSAGE_SENT,
                    [$this->resourceId],
                    [$this->resourceId => $data]
                )
            );
        }
        return $this->conn->send($data);
    }

    public function close(): void
    {
        $this->conn->close();
    }
}
