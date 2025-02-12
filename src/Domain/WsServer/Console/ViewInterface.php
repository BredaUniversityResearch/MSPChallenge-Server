<?php

namespace App\Domain\WsServer\Console;

use App\Domain\Common\Stopwatch\Stopwatch;
use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientConnectionResourceManagerInterface;

interface ViewInterface
{
    public function getName(): string;
    public function setClientConnectionResourceManager(
        ClientConnectionResourceManagerInterface $clientConnectionResourceManager
    ): void;
    public function setStopwatch(Stopwatch $stopwatch): void;
    public function notifyWsServerDataChange(NameAwareEvent $event): void;
    public function isRenderingEnabled(): bool;
    public function setRenderingEnabled(bool $enabled): void;
}
