<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\WsServer\ClientConnectionResourceManagerInterface;
use App\Domain\WsServer\MeasurementCollectionManagerInterface;
use App\Domain\WsServer\ServerManagerInterface;
use Exception;
use React\EventLoop\LoopInterface;
use Closure;
use App\Domain\Event\NameAwareEvent;

abstract class Plugin implements PluginInterface
{
    private string $name;
    private float $minIntervalSec;
    private bool $debugOutputEnabled;
    private ?int $gameSessionId = null;

    private ?LoopInterface $loop = null;
    private ?MeasurementCollectionManagerInterface $measurementCollectionManager = null;
    private ?ClientConnectionResourceManagerInterface $clientConnectionResourceManager = null;
    private ?ServerManagerInterface $serverManager = null;

    public function __construct(
        string $name,
        float $minIntervalSec,
        bool $debugOutputEnabled = true
    ) {
        $this->name = $name;
        $this->minIntervalSec = $minIntervalSec;
        $this->debugOutputEnabled = $debugOutputEnabled;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMinIntervalSec(): float
    {
        return $this->minIntervalSec;
    }

    public function isDebugOutputEnabled(): bool
    {
        return $this->debugOutputEnabled;
    }

    public function setDebugOutputEnabled(bool $debugOutputEnabled): void
    {
        $this->debugOutputEnabled = $debugOutputEnabled;
    }

    public function registerLoop(LoopInterface $loop)
    {
        $this->loop = $loop;
        $loop->futureTick(PluginHelper::createRepeatedFunction(
            $this,
            $loop,
            $this->onCreatePromiseFunction(),
            $this->debugOutputEnabled
        ));
    }

    public function getGameSessionId(): ?int
    {
        return $this->gameSessionId;
    }

    public function setGameSessionId(?int $gameSessionId): self
    {
        $this->gameSessionId = $gameSessionId;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function getLoop(): LoopInterface
    {
        if (null === $this->loop) {
            throw new Exception('Attempt to retrieve unknown loop');
        }
        return $this->loop;
    }

    /**
     * @throws Exception
     */
    public function getMeasurementCollectionManager(): MeasurementCollectionManagerInterface
    {
        if (null === $this->measurementCollectionManager) {
            throw new Exception('Attempt to retrieve unknown measurement collection manager');
        }
        return $this->measurementCollectionManager;
    }

    public function setMeasurementCollectionManager(
        MeasurementCollectionManagerInterface $measurementCollectionManager
    ): self {
        $this->measurementCollectionManager = $measurementCollectionManager;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function getClientConnectionResourceManager(): ClientConnectionResourceManagerInterface
    {
        if (null === $this->clientConnectionResourceManager) {
            throw new Exception('Attempt to retrieve unknown client connection resource manager');
        }
        return $this->clientConnectionResourceManager;
    }

    public function setClientConnectionResourceManager(
        ClientConnectionResourceManagerInterface $clientConnectionResourceManager
    ): self {
        $this->clientConnectionResourceManager = $clientConnectionResourceManager;
        return $this;
    }

    public function getServerManager(): ServerManagerInterface
    {
        if (null === $this->serverManager) {
            throw new Exception('Attempt to retrieve unknown server manager');
        }
        return $this->serverManager;
    }

    public function setServerManager(ServerManagerInterface $serverManager): self
    {
        $this->serverManager = $serverManager;
        return $this;
    }

    abstract protected function onCreatePromiseFunction(): Closure;

    public function onWsServerEventDispatched(NameAwareEvent $event): void
    {
        // nothing to do, can be implemented by child class.
    }
}
