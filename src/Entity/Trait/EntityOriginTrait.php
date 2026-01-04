<?php

namespace App\Entity\Trait;

trait EntityOriginTrait
{
    private ?int $originGameListId = null;
    private ?string $originSessionLogFilePath = null;

    public function getOriginGameListId(): ?int
    {
        return $this->originGameListId;
    }

    public function setOriginGameListId(int $originGameListId): static
    {
        $this->originGameListId = $originGameListId;
        return $this;
    }

    public function getOriginSessionLogFilePath(): ?string
    {
        return $this->originSessionLogFilePath;
    }

    public function setOriginSessionLogFilePath(?string $originSessionLogFilePath): static
    {
        $this->originSessionLogFilePath = $originSessionLogFilePath;
        return $this;
    }
}
