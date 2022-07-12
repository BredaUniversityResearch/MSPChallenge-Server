<?php

namespace App\Domain\WsServer;

use Exception;

class ClientDisconnectedException extends Exception
{
    private int $connResourceId;

    public function getConnResourceId(): int
    {
        return $this->connResourceId;
    }

    public function setConnResourceId(int $connResourceId): void
    {
        $this->connResourceId = $connResourceId;
    }
}
