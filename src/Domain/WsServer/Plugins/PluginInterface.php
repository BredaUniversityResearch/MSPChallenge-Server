<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\Stopwatch\Stopwatch;
use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientConnectionResourceManagerInterface;
use App\Domain\WsServer\ServerManagerInterface;
use App\Domain\WsServer\WsServerInterface;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\SerializerInterface;

interface PluginInterface
{
    public const EVENT_PLUGIN_REGISTERED = 'PLUGIN_REGISTERED';
    public const EVENT_PLUGIN_EXECUTION_STARTED = 'PLUGIN_EXECUTION_STARTED';
    public const EVENT_PLUGIN_EXECUTION_ENABLE_PROBE = 'PLUGIN_EXECUTION_ENABLE_PROFILE';
    public const EVENT_PLUGIN_EXECUTION_FINISHED = 'PLUGIN_EXECUTION_FINISHED';
    public const EVENT_PLUGIN_UNREGISTERED = 'PLUGIN_UNREGISTERED';
    public const EVENT_ARG_EXECUTION_ID = 'executionId';

    public static function getDefaultMinIntervalSec(): float;

    public function getName(): string;
    public function getMinIntervalSec(): float;
    public function isDebugOutputEnabled(): bool;
    public function setDebugOutputEnabled(bool $debugOutputEnabled): void;
    public function addOutput(string $output, int $verbosity = OutputInterface::VERBOSITY_NORMAL): static;

    public function getLoop(): LoopInterface;
    public function setLoop(LoopInterface $loop): static;
    public function isRegisteredToLoop(): bool;
    public function registerToLoop(LoopInterface $loop);
    public function unregisterFromLoop(LoopInterface $loop);

    public function getGameSessionIdFilter(): ?int;
    public function setGameSessionIdFilter(?int $gameSessionIdFilter): static;
    public function getStopwatch(): ?Stopwatch;
    public function setStopwatch(?Stopwatch $stopwatch): static;
    public function getClientConnectionResourceManager(): ClientConnectionResourceManagerInterface;
    public function setClientConnectionResourceManager(
        ClientConnectionResourceManagerInterface $clientConnectionResourceManager
    ): static;

    public function getServerManager(): ServerManagerInterface;
    public function setServerManager(ServerManagerInterface $serverManager): static;

    public function getWsServer(): WsServerInterface;
    public function setWsServer(WsServerInterface $wsServer): static;

    public function getSerializer(): SerializerInterface;
    public function setSerializer(SerializerInterface $serializer): static;

    public function onWsServerEventDispatched(NameAwareEvent $event): void;
}
