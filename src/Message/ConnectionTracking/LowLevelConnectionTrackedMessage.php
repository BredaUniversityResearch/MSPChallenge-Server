<?php

namespace App\Message\ConnectionTracking;

final class LowLevelConnectionTrackedMessage
{
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getConnectionId(): int
    {
        return (int) ($this->payload['connection_id'] ?? 0);
    }

    public function getDatabaseName(): ?string
    {
        $value = $this->payload['db_name'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getProcessName(): ?string
    {
        $value = $this->payload['process_name'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getUser(): ?string
    {
        $value = $this->payload['user'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }
}
