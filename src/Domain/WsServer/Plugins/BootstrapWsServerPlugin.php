<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Domain\WsServer\Plugins\Latest\LatestWsServerPlugin;
use App\Domain\WsServer\Plugins\Tick\TicksHandlerWsServerPlugin;
use Closure;
use Drift\DBAL\Result;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class BootstrapWsServerPlugin extends Plugin
{
    public function __construct()
    {
        parent::__construct('bootstrap', 0); // 0 meaning no interval, no repeating
    }

    protected function onCreatePromiseFunction(): Closure
    {
        return function () {
            return $this->getServerManager()->getGameSessionIds()
                ->then(function (Result $result) {
                    // collect
                    $gameSessionIds = collect($result->fetchAllRows() ?? [])
                        ->keyBy('id')
                        ->map(function ($row) {
                            return $row['id'];
                        });
                    $gameSessionId = $this->getGameSessionIdFilter();
                    if ($gameSessionId != null) {
                        $gameSessionIds = $gameSessionIds->only($gameSessionId);
                    }
                    $gameSessionIds = $gameSessionIds->all(); // to raw array

                    $this->migrations($gameSessionIds);
                    $this->registerPlugins();
                });
        };
    }

    /**
     * @throws Exception
     */
    private function migrations(array $gameSessionIds): void
    {
        // Run doctrine migrations.
        foreach ($gameSessionIds as $gameSessionId) {
            $dbName = ConnectionManager::getInstance()->getGameSessionDbName($gameSessionId);
            if ($this->getServerManager()->getDoctrineMigrationsDependencyFactoryHelper()
                ->getDependencyFactory($dbName)->getMigrationStatusCalculator()->getNewMigrations()->count() == 0) {
                // nothing to migrate
                continue;
            }
            $application = new Application(SymfonyToLegacyHelper::getInstance()->getKernel());
            $application->setAutoExit(false);
            $output = new BufferedOutput();
            $returnCode = $application->run(
                new StringInput('doctrine:migrations:migrate -vvv -n --conn=' . $dbName),
                $output
            );
            if (0 !== $returnCode) {
                throw new Exception(
                    'Failed to apply newest migrations to database: ' . $dbName . PHP_EOL . $output->fetch(),
                    $returnCode
                );
            }
        }
    }

    private function registerPlugins(): void
    {
        $this->getWsServer()->registerPlugin(new LoopStatsWsServerPlugin());
        $this->getWsServer()->registerPlugin(new TicksHandlerWsServerPlugin());
        $this->getWsServer()->registerPlugin(new LatestWsServerPlugin());
        $this->getWsServer()->registerPlugin(new ExecuteBatchesWsServerPlugin());
    }
}
