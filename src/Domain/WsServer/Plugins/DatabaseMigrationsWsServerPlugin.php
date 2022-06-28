<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Event\NameAwareEvent;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Closure;
use Drift\DBAL\Result;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DatabaseMigrationsWsServerPlugin extends Plugin
{
    public const EVENT_MIGRATIONS_FINISHED = 'EVENT_MIGRATIONS_FINISHED';

    public function __construct()
    {
        parent::__construct('migrations', 0);
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
                    $this->dispatch(
                        new NameAwareEvent(self::EVENT_MIGRATIONS_FINISHED),
                        self::EVENT_MIGRATIONS_FINISHED
                    );
                });
        };
    }

    /**
     * @throws Exception
     */
    private function migrations(array $gameSessionIds): void
    {
        wdo('Please do not shut down the websocket server now, until migrations are finished...');
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
            wdo($output->fetch());
        }
        wdo('Finished migrations');
    }
}
