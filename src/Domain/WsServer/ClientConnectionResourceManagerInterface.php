<?php

namespace App\Domain\WsServer;

use App\Domain\API\v1\Security;
use Illuminate\Support\Collection;

interface ClientConnectionResourceManagerInterface
{
    # retrieve data for all clients
    public function getClientHeadersContainer(): array;
    public function getClientInfoContainer(): array;
    public function getClientInfoPerSessionCollection(): Collection;

    # retrieve data for a single client by its connection resource id
    public function getClientConnection(int $connResourceId): ?WsServerConnection;
    public function getClientHeaders(int $connResourceId): ?array;
    public function getClientInfo(int $connResourceId): ?array;
    public function setClientInfo(int $connResourceId, string $clientInfoKey, $clientInfoValue): void;
    public function getSecurity(int $connResourceId): Security;
}