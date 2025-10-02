<?php

namespace App\Message\Docker;

abstract class ImmersiveSessionConnectionMessageBase extends DockerCommunicationMessageBase
{
    private int $gameSessionId;
    private int $immersiveSessionId;

    public function getGameSessionId(): int
    {
        return $this->gameSessionId;
    }

    public function setGameSessionId(int $gameSessionId): static
    {
        $this->gameSessionId = $gameSessionId;
        return $this;
    }

    public function getImmersiveSessionId(): int
    {
        return $this->immersiveSessionId;
    }

    public function setImmersiveSessionId(int $immersiveSessionId): static
    {
        $this->immersiveSessionId = $immersiveSessionId;
        return $this;
    }
}
