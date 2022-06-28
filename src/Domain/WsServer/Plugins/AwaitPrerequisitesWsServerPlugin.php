<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Event\NameAwareEvent;
use Closure;
use React\Promise\PromiseInterface;

class AwaitPrerequisitesWsServerPlugin extends Plugin
{
    public const EVENT_PREREQUISITES_MET = 'EVENT_PREREQUISITES_MET';

    public function __construct()
    {
        parent::__construct('bootstrap', 10);
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
            wdo('Found msp_server_manager database');
            $this->dispatch(new NameAwareEvent(self::EVENT_PREREQUISITES_MET), self::EVENT_PREREQUISITES_MET);
        })
        ->otherwise(function ($reason) {
            // Handle the rejection, and don't propagate. This is like catch without a rethrow
            wdo('Awaiting creation of msp_server_manager database');
            return null;
        });
    }

    protected function onCreatePromiseFunction(): Closure
    {
        return function () {
            return $this->checkServerManagerDbConnection();
        };
    }
}
