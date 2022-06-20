<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\WsServer\ClientConnectionResourceManagerInterface;
use App\Domain\WsServer\MeasurementCollectionManagerInterface;
use App\Domain\WsServer\ServerManagerInterface;
use App\Domain\WsServer\WsServerInterface;
use Exception;
use React\EventLoop\LoopInterface;
use Closure;
use App\Domain\Event\NameAwareEvent;

abstract class Plugin implements PluginInterface
{
    private string $name;
    private float $minIntervalSec;
    private bool $debugOutputEnabled;
    private ?int $gameSessionIdFilter = null;
    private bool $registeredToLoop = false;

    private ?LoopInterface $loop = null;
    private ?MeasurementCollectionManagerInterface $measurementCollectionManager = null;
    private ?ClientConnectionResourceManagerInterface $clientConnectionResourceManager = null;
    private ?ServerManagerInterface $serverManager = null;
    private ?WsServerInterface $wsServer = null;

    public function __construct(
        string $name,
        float $minIntervalSec,
        bool $debugOutputEnabled = false
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

    public function addDebugOutput(string $output): self
    {
        if ($this->isDebugOutputEnabled()) {
            wdo($output);
        }
        return $this;
    }

    final public function isRegisteredToLoop(): bool
    {
        return $this->registeredToLoop;
    }

    final public function registerToLoop(LoopInterface $loop)
    {
        $this->registeredToLoop = true;
        $this->loop = $loop;

        // interval sec is zero, so no interval, no repeating
        if ($this->getMinIntervalSec() < PHP_FLOAT_EPSILON) {
            $loop->futureTick($this->onCreatePromiseFunction());
            return;
        }
        $loop->addTimer(mt_rand() * $this->getMinIntervalSec() / mt_getrandmax(), PluginHelper::createRepeatedFunction(
            $this,
            $loop,
            $this->onCreatePromiseFunction()
        ));
    }

    final public function unregisterFromLoop(LoopInterface $loop)
    {
        $this->registeredToLoop = false; // Note that PluginHelper will take care of the rest.
    }
    public function getGameSessionIdFilter(): ?int
    {
        return $this->gameSessionIdFilter;
    }

    public function setGameSessionIdFilter(?int $gameSessionIdFilter): self
    {
        $this->gameSessionIdFilter = $gameSessionIdFilter;
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
            throw new Exception('Attempt to retrieve unknown MeasurementCollectionManagerInterface');
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
            throw new Exception('Attempt to retrieve unknown ClientConnectionResourceManagerInterface');
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
            throw new Exception('Attempt to retrieve unknown ServerManagerInterface');
        }
        return $this->serverManager;
    }

    public function setServerManager(ServerManagerInterface $serverManager): self
    {
        $this->serverManager = $serverManager;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function getWsServer(): WsServerInterface
    {
        if (null === $this->wsServer) {
            throw new Exception('Attempt to retrieve unknown WsServerInterface');
        }
        return $this->wsServer;
    }

    public function setWsServer(WsServerInterface $wsServer): self
    {
        $this->wsServer = $wsServer;
        return $this;
    }

    abstract protected function onCreatePromiseFunction(): Closure;

    public function onWsServerEventDispatched(NameAwareEvent $event): void
    {
        // nothing to do, can be implemented by child class.
    }
}
