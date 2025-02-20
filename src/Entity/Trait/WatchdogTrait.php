<?php

namespace App\Entity\Trait;

use App\Entity\Interface\WatchdogInterface;
use Symfony\Component\Uid\Uuid;

trait WatchdogTrait
{
    public static function getInternalServerId(): Uuid
    {
        return Uuid::fromString(WatchdogInterface::INTERNAL_SERVER_ID_RFC4122);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getServerId(): ?Uuid
    {
        return $this->serverId;
    }

    public function setServerId(Uuid $serverId): static
    {
        $this->serverId = $serverId;

        return $this;
    }
}
