<?php

namespace App\Domain\WsServer;

use Throwable;

class ExecuteBatchRejection extends \Exception
{
    private string $batchGuid;
    private mixed $reason;

    /**
     * @param string $batchGuid
     * @param string|Throwable $reason
     */
    public function __construct(string $batchGuid, string|Throwable $reason)
    {
        if (is_string($reason)) {
            $message = "Batch with GUID {$batchGuid} was rejected: {$reason}";
        } else {
            $message = "Batch with GUID {$batchGuid} was rejected due to an exception: ".$reason->getMessage();
        }
        parent::__construct($message, 0, $reason instanceof Throwable ? $reason : null);
        $this->batchGuid = $batchGuid;
        $this->reason = $reason;
    }

    public function getBatchGuid(): string
    {
        return $this->batchGuid;
    }

    public function getReason(): string|Throwable
    {
        return $this->reason;
    }
}
