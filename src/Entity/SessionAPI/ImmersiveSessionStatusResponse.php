<?php

namespace App\Entity\SessionAPI;

use Symfony\Component\Serializer\Annotation\Groups;

#[\AllowDynamicProperties]
class ImmersiveSessionStatusResponse
{
    #[Groups(['read'])]
    public ?string $message = null;

    #[Groups(['read'])]
    public mixed $payload = null;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getPayload(): mixed
    {
        return $this->payload;
    }

    public function setPayload(mixed $payload): void
    {
        $this->payload = $payload;
    }
}
