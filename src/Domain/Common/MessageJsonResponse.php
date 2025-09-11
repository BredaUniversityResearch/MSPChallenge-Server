<?php

namespace App\Domain\Common;

use Symfony\Component\HttpFoundation\JsonResponse;

class MessageJsonResponse extends JsonResponse
{
    private ?string $message;

    public function __construct($data = null, int $status = 200, array $headers = [], ?string $message = null)
    {
        $this->message = $message;
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
