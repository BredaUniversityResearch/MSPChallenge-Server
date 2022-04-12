<?php

namespace App\Domain\WsServer;

class ExecuteBatchRejection
{
    private int $batchId;

    /**
     * @var mixed
     */
    private $reason;

    /**
     * @param int $batchId
     * @param mixed $reason
     */
    public function __construct(int $batchId, $reason)
    {
        $this->batchId = $batchId;
        $this->reason = $reason;
    }

    public function getBatchId(): int
    {
        return $this->batchId;
    }

    /**
     * @return mixed
     */
    public function getReason()
    {
        return $this->reason;
    }
}