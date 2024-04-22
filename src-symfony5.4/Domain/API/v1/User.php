<?php

namespace App\Domain\API\v1;

class User extends UserBase
{
    public function getUserIdentifier(): ?int
    {
        return $this->getUserId();
    }
}
