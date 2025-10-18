<?php

namespace App\Domain\Common;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MessageJsonResponse extends JsonResponse
{
    private ?string $message;

    public function __construct($data = null, int $status = 200, array $headers = [], ?string $message = null)
    {
        $debugMessage = null;
        // @marin debug feature: setting a debug-message through a payload field
        if (is_array($data) && isset($data['debug-message'])) {
            if (is_string($data['debug-message'])) {
                $debugMessage = $data['debug-message'];
            }
            unset($data['debug-message']);
        }

        $this->message = $message;
        if ($debugMessage) {
            $this->message = ($this->message ?? '').$debugMessage;
        }

        if ($data === null) {
            Response::__construct($this->message, $status, $headers);
            return;
        }

        parent::__construct($data, $status, $headers);
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }
}
