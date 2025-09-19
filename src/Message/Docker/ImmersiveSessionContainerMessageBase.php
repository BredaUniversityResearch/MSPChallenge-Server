<?php

namespace App\Message\Docker;

abstract class ImmersiveSessionContainerMessageBase extends DockerCommunicationMessageBase
{
    private int $immersiveSessionId;

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
