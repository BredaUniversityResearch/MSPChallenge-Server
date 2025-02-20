<?php

namespace App\Entity\Interface;

use Symfony\Component\Uid\Uuid;

interface WatchdogInterface
{
    public const DEFAULT_ADDRESS = 'simulations';
    public const DEFAULT_PORT = 80;
    public const INTERNAL_SERVER_ID_RFC4122 = '019373cc-aa68-7d95-882f-9248ea338014';

    public static function getInternalServerId(): Uuid;

    public function getId(): ?int;
    public function setId(int $id): static;
    public function getServerId(): ?Uuid;
    public function setServerId(Uuid $serverId): static;
}
