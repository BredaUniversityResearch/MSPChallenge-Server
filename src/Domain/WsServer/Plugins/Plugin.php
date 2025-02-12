<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\Context;
use App\Domain\Common\Stopwatch\Stopwatch;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientConnectionResourceManagerInterface;
use App\Domain\WsServer\ServerManagerInterface;
use App\Domain\WsServer\WsServerInterface;
use App\Domain\WsServer\WsServerOutput;
use Exception;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function App\tpf;

abstract class Plugin extends EventDispatcher implements PluginInterface
{
    private string $name;
    private float $minIntervalSec;
    private bool $debugOutputEnabled;
    private int $messageVerbosity = WsServerOutput::VERBOSITY_DEFAULT_MESSAGE;
    private ?int $gameSessionIdFilter = null;
    private bool $registeredToLoop = false;

    private ?LoopInterface $loop = null;
    private ?Stopwatch $stopwatch = null;
    private ?ClientConnectionResourceManagerInterface $clientConnectionResourceManager = null;
    private ?ServerManagerInterface $serverManager = null;
    private ?WsServerInterface $wsServer = null;

    public function __construct(
        string $name,
        ?float $minIntervalSec = null,
        bool $debugOutputEnabled = true
    ) {
        $this->name = $name;
        $this->minIntervalSec ??= $minIntervalSec ?? static::getDefaultMinIntervalSec();
        $this->debugOutputEnabled = $debugOutputEnabled;
        parent::__construct();
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

    public function getMessageVerbosity(): int
    {
        return $this->messageVerbosity;
    }

    public function setMessageVerbosity(int $messageVerbosity): void
    {
        $this->messageVerbosity = $messageVerbosity;
    }

    public function addOutput(string $output, ?int $verbosity = null): self
    {
        $verbosity ??= $this->messageVerbosity;
        if ($this->isDebugOutputEnabled()) {
            wdo($output, $verbosity);
        }
        return $this;
    }

    final public function isRegisteredToLoop(): bool
    {
        return $this->registeredToLoop;
    }

    /**
     * @throws Exception
     */
    final public function registerToLoop(LoopInterface $loop): void
    {
        $this->dispatch(
            new NameAwareEvent(self::EVENT_PLUGIN_REGISTERED, $this),
            self::EVENT_PLUGIN_REGISTERED
        );
        $this->registeredToLoop = true;
        $this->loop = $loop;

        // interval sec is zero, so no interval, no repeating
        if ($this->getMinIntervalSec() < PHP_FLOAT_EPSILON) {
            $loop->futureTick($this->createPromiseFunction());
            return;
        }
        $loop->addTimer(
            mt_rand() * $this->getMinIntervalSec() / mt_getrandmax(),
            PluginHelper::getInstance()->createRepeatedFunction(
                $this,
                $loop,
                function () {
                    return ($this->createPromiseFunction())();
                }
            )
        );
    }

    final public function unregisterFromLoop(LoopInterface $loop): void
    {
        $this->dispatch(
            new NameAwareEvent(self::EVENT_PLUGIN_UNREGISTERED, $this),
            self::EVENT_PLUGIN_UNREGISTERED
        );
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

    public function setLoop(LoopInterface $loop): self
    {
        $this->loop = $loop;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function getStopwatch(): ?Stopwatch
    {
        return $this->stopwatch;
    }

    public function setStopwatch(?Stopwatch $stopwatch): self
    {
        $this->stopwatch = $stopwatch;
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

    protected function createPromiseFunction(?string $executionId = null): ToPromiseFunction
    {
        return tpf(function () use ($executionId) {
            $executionId ??= uniqid();
            $this->dispatch(
                new NameAwareEvent(
                    self::EVENT_PLUGIN_EXECUTION_STARTED,
                    $this,
                    [self::EVENT_ARG_EXECUTION_ID => $executionId]
                ),
                self::EVENT_PLUGIN_EXECUTION_STARTED
            );
            $tpf = $this->onCreatePromiseFunction($executionId);
            $context = Context::root()->enter($this->getName());
            $tpf->setContext($context);
            $this->getStopwatch()?->start($context->getPath());
            return ($tpf)()
                ->then(function () use ($executionId, $context) {
                    $this->getStopwatch()?->stop($context->getPath());
                    $this->addOutput(
                        'Plugin '.$this->getName().' just finished',
                        OutputInterface::VERBOSITY_DEBUG
                    );
                    $this->dispatch(
                        new NameAwareEvent(
                            self::EVENT_PLUGIN_EXECUTION_FINISHED,
                            $this,
                            [self::EVENT_ARG_EXECUTION_ID => $executionId]
                        ),
                        self::EVENT_PLUGIN_EXECUTION_FINISHED
                    );
                });
        });
    }

    abstract protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction;

    public function onWsServerEventDispatched(NameAwareEvent $event): void
    {
        // nothing to do, can be implemented by child class.
    }
}
