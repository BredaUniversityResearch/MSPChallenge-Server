<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientConnectionResourceManagerInterface;
use App\Domain\WsServer\MeasurementCollectionManagerInterface;
use App\Domain\WsServer\ServerManagerInterface;
use App\Domain\WsServer\WsServerInterface;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface PluginInterface
{
    public static function getDefaultMinIntervalSec(): float;

    public function getName(): string;
    public function getMinIntervalSec(): float;
    public function isDebugOutputEnabled(): bool;
    public function setDebugOutputEnabled(bool $debugOutputEnabled): void;
    public function addOutput(string $output, int $verbosity = OutputInterface::VERBOSITY_NORMAL): self;

    public function isRegisteredToLoop(): bool;
    public function registerToLoop(LoopInterface $loop);
    public function unregisterFromLoop(LoopInterface $loop);

    public function getGameSessionIdFilter(): ?int;
    public function setGameSessionIdFilter(?int $gameSessionIdFilter): self;

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

    public function getWsServer(): WsServerInterface;
    public function setWsServer(WsServerInterface $wsServer): self;

    public function onWsServerEventDispatched(NameAwareEvent $event): void;
}
