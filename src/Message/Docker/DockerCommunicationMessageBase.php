<?php

namespace App\Message\Docker;

abstract class DockerCommunicationMessageBase
{
    private int $gameSessionId;

    public function getGameSessionId(): int
    {
        return $this->gameSessionId;
    }

    public function setGameSessionId(int $gameSessionId): self
    {
        $this->gameSessionId = $gameSessionId;
        return $this;
    }
}
