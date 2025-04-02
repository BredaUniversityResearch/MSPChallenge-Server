<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\API\v1\Simulation;
use App\Domain\Common\Context;
use App\Domain\Common\ToPromiseFunction;
use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\tpf;
use function React\Promise\resolve;

class WatchdogPingWsServerPlugin extends Plugin
{
    public static function getDefaultMinIntervalSec(): float
    {
        return 30;
    }

    public function __construct(?float $minIntervalSec = null)
    {
        parent::__construct('watchdogPing', $minIntervalSec);
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
                    return $this->pingWatchdogs($gameSessionIds);
                });
        });
    }

    /**
     * @throws Exception
     */
    private function pingWatchdogs(array $gameSessionIds): PromiseInterface
    {
        $toPromiseFunctions = [];
        foreach ($gameSessionIds as $gameSessionId) {
            $simulation = new Simulation();
            $simulation
                ->setAsync(true)
                ->setGameSessionId($gameSessionId)
                ->setAsyncDatabase($this->getServerManager()->getGameSessionDbConnection($gameSessionId));
            $toPromiseFunctions[] = tpf(fn() => $simulation->pingWatchdogs());
        }
        if (empty($toPromiseFunctions)) {
            return resolve();
        }
        return parallel($toPromiseFunctions);
    }
}
