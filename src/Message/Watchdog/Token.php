<?php

namespace App\Message\Watchdog;

class Token
{
    private string $token;
    private \DateTime $validUntil;

    public function __construct(string $token, \DateTime $validUntil)
    {
        $this->token = $token;
        $this->validUntil = $validUntil;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getValidUntil(): \DateTime
    {
        return $this->validUntil;
    }
}
