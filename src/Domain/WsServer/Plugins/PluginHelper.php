<?php

namespace App\Domain\WsServer\Plugins;

use Closure;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
            // plugin should be unregistered from loop
            if (!$plugin->isRegisteredToLoop()) {
                $plugin->addOutput(
                    'Unregistered from loop: "' . $plugin->getName() .'"',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                return;
            }
            $elapsedSec = (microtime(true) - $startTime) * 0.000001;
            if ($elapsedSec > $plugin->getMinIntervalSec()) {
                $plugin->addOutput(
                    'starting new future "' . $plugin->getName() .'"',
                    OutputInterface::VERBOSITY_VERY_VERBOSE
                );
                $loop->futureTick($repeatedFunction);
                return;
            }
            $waitingSec = $plugin->getMinIntervalSec() - $elapsedSec;
            $plugin->addOutput(
                'awaiting new future "' . $plugin->getName() . '" for ' . $waitingSec . ' sec',
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );
            $loop->addTimer($waitingSec, function () use ($plugin, $loop, $repeatedFunction) {
                $plugin->addOutput(
                    'starting new future "' . $plugin->getName() . '"',
                    OutputInterface::VERBOSITY_VERY_VERBOSE
                );
                $loop->futureTick($repeatedFunction);
            });
        };
    }
}
