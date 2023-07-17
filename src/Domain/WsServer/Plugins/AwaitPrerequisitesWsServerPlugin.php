<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\ToPromiseFunction;
use App\Domain\Event\NameAwareEvent;
use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function App\tpf;

class AwaitPrerequisitesWsServerPlugin extends Plugin
{
    public const EVENT_PREREQUISITES_MET = 'EVENT_PREREQUISITES_MET';

    public static function getDefaultMinIntervalSec(): float
    {
        return 10;
    }

    public function __construct(?float $minIntervalSec = null)
    {
        parent::__construct('await prerequisites', $minIntervalSec);
        $this->setMessageVerbosity(OutputInterface::VERBOSITY_NORMAL);
    }

    private function checkServerManagerDbConnection(): PromiseInterface
    {
        $connection = $this->getServerManager()->getServerManagerDbConnection();
        $qb = $connection->createQueryBuilder();
        return $connection->query(
            $qb
                ->select('name')
                ->from('settings')
                ->where($qb->expr()->eq('name', $qb->createPositionalParameter('server_id')))
                ->setMaxResults(1)
        )
        ->then(function (Result $result) {
            if ($result->fetchCount() == 0) {
                throw new Exception('Required server_manager settings record not found');
            }
            $this->addOutput('Found msp_server_manager database');
            $this->dispatch(new NameAwareEvent(self::EVENT_PREREQUISITES_MET, $this), self::EVENT_PREREQUISITES_MET);
        })
        ->otherwise(function ($reason) {
            // Handle the rejection, and don't propagate. This is like catch without a rethrow
            $this->addOutput('Awaiting creation of msp_server_manager database: '.$reason->getMessage());
            return null;
        });
    }

    protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction
    {
        return tpf(function () {
            return $this->checkServerManagerDbConnection();
        });
    }
}
