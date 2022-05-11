<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientConnectionResourceManagerInterface;
use App\Domain\WsServer\MeasurementCollectionManagerInterface;
use App\Domain\WsServer\ServerManagerInterface;
use React\EventLoop\LoopInterface;

interface PluginInterface
{
    public function getName(): string;
    public function getMinIntervalSec(): float;
    public function isDebugOutputEnabled(): bool;
    public function setDebugOutputEnabled(bool $debugOutputEnabled): void;

    public function registerLoop(LoopInterface $loop);

    public function getGameSessionId(): ?int;
    public function setGameSessionId(?int $gameSessionId): self;

    public function getMeasurementCollectionManager(): MeasurementCollectionManagerInterface;
    public function setMeasurementCollectionManager(
        MeasurementCollectionManagerInterface $measurementCollectionManager
    ): self;
    public function getClientConnectionResourceManager(): ClientConnectionResourceManagerInterface;
    public function setClientConnectionResourceManager(
        ClientConnectionResourceManagerInterface $clientConnectionResourceManager
    ): self;

    public function getServerManager(): ServerManagerInterface;
    public function setServerManager(ServerManagerInterface $serverManager): self;

    public function onWsServerEventDispatched(NameAwareEvent $event): void;
}
