<?php

namespace App\Message\Docker;

class RemoveImmersiveSessionContainerMessage extends ImmersiveSessionContainerMessageBase
{
    private string $dockerContainerId;

    public function getDockerContainerId(): string
    {
        return $this->dockerContainerId;
    }

    public function setDockerContainerId(string $dockerContainerId): static
    {
        $this->dockerContainerId = $dockerContainerId;
        return $this;
    }
}
