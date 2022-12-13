<?php

namespace App\Domain\WsServer\Plugins;

use Closure;
use React\Promise\Deferred;
use function App\resolveOnFutureTick;

class LoopStatsWsServerPlugin extends Plugin
{
    private int $loop = 1;

    public function __construct()
    {
        parent::__construct('loop', 0);
    }

    protected function onCreatePromiseFunction(): Closure
    {
        return function () {
            $latestTimeStart = microtime(true);
            return resolveOnFutureTick(new Deferred())->promise()->then(function () use ($latestTimeStart) {
                $this->getMeasurementCollectionManager()->addToMeasurementCollection(
                    $this->getName(),
                    (string)$this->loop++,
                    microtime(true) - $latestTimeStart
                );
            });
        };
    }
}
