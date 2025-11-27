<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\Context;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\Plugins\Latest\LatestWsServerPlugin;
use App\Domain\WsServer\Plugins\Tick\TicksHandlerWsServerPlugin;
use Exception;
use React\Promise\Deferred;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function App\resolveOnFutureTick;
use function App\tpf;

class BootstrapWsServerPlugin extends Plugin implements EventSubscriberInterface
{
    private const STATE_NONE                = 0;
    private const STATE_AWAIT_PREREQUISITES = 1;
    private const STATE_DATABASE_MIGRATIONS = 2;
    private const STATE_REGISTER_PLUGINS    = 3;
    private const STATE_READY               = 4;

    private AwaitPrerequisitesWsServerPlugin $awaitPrerequisitesPlugin;
    private DatabaseMigrationsWsServerPlugin $databaseMigrationsPlugin;
    private int $state = self::STATE_NONE;

    public static function getDefaultMinIntervalSec(): float
    {
        return 0; // 0 meaning no interval, no repeating
    }

    public function __construct()
    {
        parent::__construct('bootstrap');
        $this->setMessageVerbosity(OutputInterface::VERBOSITY_NORMAL);
        $this->awaitPrerequisitesPlugin = $this->createAwaitPrerequisitesPlugin();
        $this->databaseMigrationsPlugin = $this->createDatabaseMigrationsPlugin();
    }

    protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction
    {
        return tpf(function (?Context $context) {
            $this->changeState(self::STATE_AWAIT_PREREQUISITES);
            // meaning bootstrap plugin itself will not register any promises, but since it calls registerPlugin
            //   on other plugins, those will be registered
            return resolveOnFutureTick(new Deferred())->promise();
        });
    }

    /**
     * @throws Exception
     */
    private function changeState(int $state)
    {
        if ($this->state == $state) {
            return; // no change
        }
        $this->endState($this->state);
        $this->startState($state);
        $this->state = $state;
    }

    /**
     * @throws Exception
     */
    private function startState(int $state)
    {
        switch ($state) {
            case self::STATE_AWAIT_PREREQUISITES:
                $this->startStateAwaitPrerequisites();
                break;
            case self::STATE_DATABASE_MIGRATIONS:
                $this->startStateDatabaseMigrations();
                break;
            case self::STATE_REGISTER_PLUGINS:
                $this->startStateRegisterPlugins();
                break;
            case self::STATE_READY:
                $this->addOutput('*** Websocket server is ready *** ');
                $this->addOutput('Press "n" -> [Enter] to switch views');
                break;
        }
    }

    /**
     * @throws Exception
     */
    private function endState(int $state)
    {
        switch ($state) {
            case self::STATE_AWAIT_PREREQUISITES:
                $this->endStateAwaitPrerequisites();
                break;
            case self::STATE_DATABASE_MIGRATIONS:
                $this->endStateDatabaseMigrations();
                break;
        }
    }

    /**
     * @throws Exception
     */
    private function startStateRegisterPlugins(): void
    {
        // create plugins
        $ticksHandlerPlugin = new TicksHandlerWsServerPlugin();
        $sequencerPlugin = new SequencerWsServerPlugin([
            ExecuteBatchesWsServerPlugin::class,
            LatestWsServerPlugin::class
        ]);
        $watchdogPingPlugin = new WatchdogPingWsServerPlugin();
        $immersiveSessionsPlugin = new ImmersiveSessionsWsServerPlugin();

        // then create & register blackfire plugin
        if (($_ENV['BLACKFIRE_APM_ENABLED'] ?? false) &&
            extension_loaded('pcntl')) {
            $blackfirePlugin = new BlackfireWsServerPlugin();
            $sequencerPlugin->addSubscriber($blackfirePlugin);
            $ticksHandlerPlugin->addSubscriber($blackfirePlugin);
            $this->getWsServer()->registerPlugin($blackfirePlugin);
        }

        // register other plugins
        $this->getWsServer()->registerPlugin($sequencerPlugin);
        $this->getWsServer()->registerPlugin($ticksHandlerPlugin);
        $this->getWsServer()->registerPlugin($watchdogPingPlugin);
        $this->getWsServer()->registerPlugin($immersiveSessionsPlugin);

        // set state ready on next tick
        $this->getLoop()->futureTick(function () {
            $this->changeState(self::STATE_READY);
        });
    }

    /**
     * @throws Exception
     */
    private function startStateAwaitPrerequisites()
    {
        $this->getWsServer()->registerPlugin($this->awaitPrerequisitesPlugin);
    }

    /**
     * @throws Exception
     */
    private function endStateAwaitPrerequisites()
    {
        $this->getWsServer()->unregisterPlugin($this->awaitPrerequisitesPlugin);
    }

    /**
     * @throws Exception
     */
    private function startStateDatabaseMigrations()
    {
        $this->getWsServer()->registerPlugin($this->databaseMigrationsPlugin);
    }

    /**
     * @throws Exception
     */
    private function endStateDatabaseMigrations()
    {
        $this->getWsServer()->unregisterPlugin($this->databaseMigrationsPlugin);
    }

    private function createAwaitPrerequisitesPlugin(): AwaitPrerequisitesWsServerPlugin
    {
        $plugin = new AwaitPrerequisitesWsServerPlugin();
        $plugin->addSubscriber($this);
        return $plugin;
    }

    private function createDatabaseMigrationsPlugin(): DatabaseMigrationsWsServerPlugin
    {
        $plugin = new DatabaseMigrationsWsServerPlugin();
        $plugin->addSubscriber($this);
        return $plugin;
    }

    /**
     * @throws Exception
     */
    public function onEvent(NameAwareEvent $event)
    {
        /** @var Plugin $plugin */
        $plugin = $event->getSubject(); // the plugin that just finished
        if (($plugin instanceof AwaitPrerequisitesWsServerPlugin) &&
            $event->getEventName() === AwaitPrerequisitesWsServerPlugin::EVENT_PREREQUISITES_MET) {
            $this->changeState(self::STATE_DATABASE_MIGRATIONS);
            return;
        }
        if ($event->getEventName() != self::EVENT_PLUGIN_EXECUTION_FINISHED) {
            return;
        }
        if (!($plugin instanceof DatabaseMigrationsWsServerPlugin)) {
            return;
        }
        $this->changeState(self::STATE_REGISTER_PLUGINS);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AwaitPrerequisitesWsServerPlugin::EVENT_PREREQUISITES_MET => 'onEvent',
            self::EVENT_PLUGIN_EXECUTION_FINISHED => 'onEvent'
        ];
    }
}
