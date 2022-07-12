<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\Plugins\Latest\LatestWsServerPlugin;
use App\Domain\WsServer\Plugins\Tick\TicksHandlerWsServerPlugin;
use Closure;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        parent::__construct('bootstrap', 0); // 0 meaning no interval, no repeating
        $this->setMessageVerbosity(OutputInterface::VERBOSITY_NORMAL);
        $this->awaitPrerequisitesPlugin = $this->createAwaitPrerequisitesPlugin();
        $this->databaseMigrationsPlugin = $this->createDatabaseMigrationsPlugin();
    }

    protected function onCreatePromiseFunction(): Closure
    {
        return function () {
            $this->changeState(self::STATE_AWAIT_PREREQUISITES);
        };
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
                $this->addOutput('Websocket server is ready');
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
    private function startStateRegisterPlugins()
    {
        $this->getWsServer()->registerPlugin(new LoopStatsWsServerPlugin());
        $this->getWsServer()->registerPlugin(new TicksHandlerWsServerPlugin());
        $this->getWsServer()->registerPlugin(new LatestWsServerPlugin($this->projectDir));
        $this->getWsServer()->registerPlugin(new ExecuteBatchesWsServerPlugin());

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
        switch ($event->getEventName()) {
            case AwaitPrerequisitesWsServerPlugin::EVENT_PREREQUISITES_MET:
                $this->changeState(self::STATE_DATABASE_MIGRATIONS);
                break;
            case DatabaseMigrationsWsServerPlugin::EVENT_MIGRATIONS_FINISHED:
                $this->changeState(self::STATE_REGISTER_PLUGINS);
                break;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AwaitPrerequisitesWsServerPlugin::EVENT_PREREQUISITES_MET => 'onEvent',
            DatabaseMigrationsWsServerPlugin::EVENT_MIGRATIONS_FINISHED => 'onEvent'
        ];
    }
}
