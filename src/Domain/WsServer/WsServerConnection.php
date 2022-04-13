<?php

namespace App\Domain\WsServer;

use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;

class WsServerConnection
{
    private ConnectionInterface $conn;
    public RequestInterface $httpRequest;
    public int $resourceId;

    public function __construct(ConnectionInterface $conn)
    {
        $this->conn = $conn;
        /** @noinspection PhpUndefinedFieldInspection */
        $this->httpRequest = $conn->httpRequest;
        /** @noinspection PhpUndefinedFieldInspection */
        $this->resourceId = $conn->resourceId;
    }

    public function send($data): ConnectionInterface
    {
        return $this->conn->send($data);
    }

    public function close(): void
    {
        $this->conn->close();
    }
}
