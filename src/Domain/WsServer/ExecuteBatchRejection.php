<?php

namespace App\Domain\WsServer;

class ExecuteBatchRejection
{
    private string $batchGuid;
    private mixed $reason;

    /**
     * @param string $batchGuid
     * @param mixed $reason
     */
    public function __construct(string $batchGuid, mixed $reason)
    {
        $this->batchGuid = $batchGuid;
        $this->reason = $reason;
    }

    public function getBatchGuid(): string
    {
        return $this->batchGuid;
    }

    public function getReason(): mixed
    {
        return $this->reason;
    }
}
