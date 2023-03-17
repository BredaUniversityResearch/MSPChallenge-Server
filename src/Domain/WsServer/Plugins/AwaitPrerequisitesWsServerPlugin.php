<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\ToPromiseFunction;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function App\tpf;

class AwaitPrerequisitesWsServerPlugin extends Plugin
{
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
                ->setMaxResults(1)
        )
        ->then(function (/* Result $resul t*/) {
            $this->addOutput('Found msp_server_manager database');
        })
        ->otherwise(function ($reason) {
            // Handle the rejection, and don't propagate. This is like catch without a rethrow
            $this->addOutput('Awaiting creation of msp_server_manager database');
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
