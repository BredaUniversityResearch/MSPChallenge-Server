<?php

namespace App\Domain\WsServer\Plugins;

use Closure;
use React\EventLoop\LoopInterface;
use function App\assertFulfilled;

class PluginHelper
{
    public static function createRepeatedFunction(
        PluginInterface $plugin,
        LoopInterface $loop,
        Closure $promiseFunction
    ): Closure {
        return function () use ($plugin, $loop, $promiseFunction) {
            $startTime = microtime(true);
            assertFulfilled(
                $promiseFunction(),
                self::createRepeatedOnFulfilledFunction(
                    $plugin,
                    $loop,
                    $startTime,
                    self::createRepeatedFunction($plugin, $loop, $promiseFunction)
                )
            );
        };
    }

    private static function createRepeatedOnFulfilledFunction(
        PluginInterface $plugin,
        LoopInterface $loop,
        float $startTime,
        Closure $repeatedFunction
    ): Closure {
        return function () use ($plugin, $loop, $startTime, $repeatedFunction) {
            $elapsedSec = (microtime(true) - $startTime) * 0.000001;
            if ($elapsedSec > $plugin->getMinIntervalSec()) {
                if ($plugin->isDebugOutputEnabled()) {
                    wdo('starting new future "' . $plugin->getName() .'"');
                }
                $loop->futureTick($repeatedFunction);
                return;
            }
            $waitingSec = $plugin->getMinIntervalSec() - $elapsedSec;
            if ($plugin->isDebugOutputEnabled()) {
                wdo('awaiting new future "' . $plugin->getName() . '" for ' . $waitingSec . ' sec');
            }
            $loop->addTimer($waitingSec, function () use ($plugin, $loop, $repeatedFunction) {
                if ($plugin->isDebugOutputEnabled()) {
                    wdo('starting new future "' . $plugin->getName() . '"');
                }
                $loop->futureTick($repeatedFunction);
            });
        };
    }
}
