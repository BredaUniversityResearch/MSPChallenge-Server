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

    /**
     * Underlying connection resource ids (PHP resource/stream ids) can be recycled by PHP and reused for a
     * brand-new connection once a previous one has been closed and garbage collected. This is especially likely
     * on long-running ws-servers with many client connects/disconnects. This sequence number is unique for the
     * lifetime of one specific connection, and is never reused, so it can be used to reliably detect whether the
     * connection currently associated with a given $connResourceId is still the same one that was there when
     * some earlier async work was started for it.
     *
     * See docs/ws-server-client-connection-sequence.md for the full mechanism and concrete examples.
     */
    public function getClientConnectionSequence(int $connResourceId): ?int;
}
