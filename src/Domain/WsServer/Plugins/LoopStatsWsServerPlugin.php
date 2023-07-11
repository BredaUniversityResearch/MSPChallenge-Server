<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\ToPromiseFunction;
use React\Promise\Deferred;
use function App\resolveOnFutureTick;
use function App\tpf;

class LoopStatsWsServerPlugin extends Plugin
{
    private int $loop = 1;

    public static function getDefaultMinIntervalSec(): float
    {
        return PHP_FLOAT_EPSILON * 2; // like as quick as possible
    }

    public function __construct()
    {
        parent::__construct('loop');
    }

    protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction
    {
        return tpf(function () {
            $latestTimeStart = microtime(true);
            return resolveOnFutureTick(new Deferred())->promise()->then(function () use ($latestTimeStart) {
                $this->getMeasurementCollectionManager()->addToMeasurementCollection(
                    $this->getName(),
                    (string)$this->loop++,
                    microtime(true) - $latestTimeStart
                );
            });
        });
    }
}
