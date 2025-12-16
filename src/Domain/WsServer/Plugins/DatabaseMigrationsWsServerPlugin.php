<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\Context;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Drift\DBAL\Result;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use function App\tpf;


class DatabaseMigrationsWsServerPlugin extends Plugin
{
    public static function getDefaultMinIntervalSec(): float
    {
        return 0; // 0 meaning no interval, no repeating
    }

    public function __construct()
    {
        parent::__construct('migrations');
        $this->setMessageVerbosity(OutputInterface::VERBOSITY_NORMAL);
    }

    protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction
    {
        return tpf(function (?Context $context) {
            return $this->getServerManager()->getGameSessionIds()
                ->then(function (Result $result) {
                    // collect
                    $gameSessionIds = collect(($result->fetchAllRows() ?? []) ?: [])
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
                });
        });
    }

    /**
     * @throws Exception
     */
    private function migrations(array $gameSessionIds): void
    {
        $this->addOutput('Please do not shut down the websocket server now, until migrations are finished...');
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
                new StringInput('doctrine:migrations:migrate -vvv -n --em=' . $dbName),
                $output
            );
            if (0 !== $returnCode) {
                throw new Exception(
                    'Failed to apply newest migrations to database: ' . $dbName . PHP_EOL . $output->fetch(),
                    $returnCode
                );
            }
            $this->addOutput($output->fetch());
        }
        $this->addOutput('Finished migrations');
    }
}
