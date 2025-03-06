<?php

// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Common\Context;
use App\Domain\Common\Stopwatch\Stopwatch;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\tpf;
use function App\chain;

$context = Context::root();
getStopwatch()->start($context->getPath());
$numClients = 3;
for ($i = 0; $i < $numClients; $i++) {
    level1($context);
}
getStopwatch()->stop($context->getPath());
outputEvents();


/**
 * Outputs all events from the stopwatch
 *
 * e.g.
 *   default/root: 8.00 MiB - 3695 ms
 *   default/root.level1: 8.00 MiB - 3695 ms
 *   default/root.level1.level2a: 8.00 MiB - 1817 ms
 *   default/root.level1.level2b: 8.00 MiB - 1817 ms
 *   default/root.level1.level2a.level3: 8.00 MiB - 1512 ms
 *   default/root.level1.level2b.level3: 8.00 MiB - 1513 ms
 */
function outputEvents(): void
{
    foreach (getStopwatch()->getSections() as $section) {
        $events = $section->getEvents();
        foreach ($events as $event) {
            echo $event.PHP_EOL;
        }
    }
}

function getStopwatch(bool $reCreate = false): Stopwatch
{
    static $stopwatch = null;
    if ($reCreate) {
        $stopwatch = null;
    }
    $stopwatch ??= new Stopwatch();
    return $stopwatch;
}

function level1(Context $context): void
{
    $context = $context->enter('level1');
    getStopwatch()->start($context->getPath());
    usleep(20000); // called 3 x 20 ms = 0.06 seconds
    for ($i = 0; $i < 5; $i++) {
        level2a($context);
        level2b($context);
    }
    getStopwatch()->stop($context->getPath());
}

function level2a(Context $context): void
{
    $context = $context->enter('level2a');
    getStopwatch()->start($context->getPath());
    usleep(20000); // called 15 x 20 ms = 0.3 seconds
    for ($i = 0; $i < 5; $i++) {
        level3($context);
    }
    getStopwatch()->stop($context->getPath());
}

function level2b(Context $context): void
{
    $context = $context->enter('level2b');
    getStopwatch()->start($context->getPath());
    usleep(20000); // called 15 x 20 ms = 0.3 seconds
    for ($i = 0; $i < 5; $i++) {
        level3($context);
    }
    getStopwatch()->stop($context->getPath());
}

function level3(Context $context): void
{
    $context = $context->enter('level3');
    getStopwatch()->start($context->getPath());
    usleep(20000); // called 75 x 20 ms = 1.5 seconds
    getStopwatch()->stop($context->getPath());
}

// test with async code
echo 'Async test'.PHP_EOL.PHP_EOL;
$context = Context::root();
getStopwatch(true)->start($context->getPath());
$tasks = [];
for ($i = 0; $i < $numClients; $i++) {
    $tasks[] = tpf(fn() => level1Async($context)); // make a copy of the context on each async call
}
chain($tasks)->then(
    function () use ($context) {
        getStopwatch()->stop($context->getPath());
        outputEvents();
    }
);

function runAfterDelay($delay, $callback): PromiseInterface
{
    $deferred = new Deferred();

    $loop = React\EventLoop\Loop::get();
    $loop->addPeriodicTimer($delay, function ($timer) use ($callback, $deferred, $loop) {
        $deferred->resolve($callback($timer));
        $loop->cancelTimer($timer);
    });

    return $deferred->promise();
}

function level1Async(Context $context): PromiseInterface
{
    $context = $context->enter('level1');
    getStopwatch()->start($context->getPath());
    return runAfterDelay(
        0.02, // called 15 x 20 ms = 0.3 seconds
        function () use ($context) {
            $tasks = [];
            for ($i = 0; $i < 5; $i++) {
                $tasks[] = tpf(fn() => level2aAsync($context));
                $tasks[] = tpf(fn() => level2bAsync($context));
            }
            return chain($tasks)
                ->then(function () use ($context) {
                    getStopwatch()->stop($context->getPath());
                });
        }
    );
}

function level2aAsync(Context $context): PromiseInterface
{
    $context = $context->enter('level2a');
    getStopwatch()->start($context->getPath());
    return runAfterDelay(
        0.02, // called 15 x 20 ms = 0.3 seconds
        function () use ($context) {
            $tasks = [];
            for ($i = 0; $i < 5; $i++) {
                $tasks[] = tpf(fn() => level3Async($context));
            }
            return chain($tasks)
                ->then(function () use ($context) {
                    getStopwatch()->stop($context->getPath());
                });
        }
    );
}

function level2bAsync(Context $context): PromiseInterface
{
    $context = $context->enter('level2b');
    getStopwatch()->start($context->getPath());
    return runAfterDelay(
        0.02, // called 15 x 20 ms = 0.3 seconds
        function () use ($context) {
            $tasks = [];
            for ($i = 0; $i < 5; $i++) {
                $tasks[] = tpf(fn() => level3Async($context));
            }
            return chain($tasks)
                ->then(function () use ($context) {
                    getStopwatch()->stop($context->getPath());
                });
        }
    );
}


function level3Async(Context $context): PromiseInterface
{
    $context = $context->enter('level3');
    getStopwatch()->start($context->getPath());
    return runAfterDelay(
        0.02, // called 75 x 20 ms = 1.5 seconds
        function () use ($context) {
            getStopwatch()->stop($context->getPath());
        }
    );
}
