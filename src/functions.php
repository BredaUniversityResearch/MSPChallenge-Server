<?php

namespace App;

use Closure;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\all;

function resolveOnFutureTick(Deferred $deferred, $resolveValue = null, ?LoopInterface $loop = null): Deferred
{
    if (null === $loop) {
        $loop = Loop::get();
    }
    // resolve on future tick, to allow caller to attach an "onfulfilled"
    $loop->futureTick(function () use ($deferred, $resolveValue) {
        $deferred->resolve($resolveValue);
    });
    return $deferred;
}

function assertFulfilled(PromiseInterface $promise, ?Closure $onFullfulled = null): void
{
    $promise->done(
        $onFullfulled,
        function (string $reason) {
            assert(false, $reason);
        }
    );
}

/**
 * @param PromiseInterface[] $promises
 */
function chain(array $promises): ?PromiseInterface
{
    $deferred = new Deferred();
    if (false === $promise = reset($promises)) {
        return resolveOnFutureTick($deferred, [])->promise();
    }
    $results = [];
    $nextKey = key($promises);
    while ($next = next($promises)) {
        $promise = $promise->then(function ($result) use ($next, &$results, $nextKey) {
             $results[$nextKey] = $result;
             return $next;
        });
        $nextKey = key($promises);
    }
    $promise
        ->then(function ($result) use ($deferred, &$results, $nextKey) {
            $results[$nextKey] = $result;
            $deferred->resolve($results);
        });
    return $deferred->promise();
}

function parallel(array $promises, int $numThreads)
{
    $numThreads = max(1, $numThreads); // should be at least one
    if (empty($promises)) {
        return resolveOnFutureTick(new Deferred(), [])->promise();
    }

    $numPromisesPerThread = count($promises) / $numThreads;
    $threads = array_chunk($promises, $numPromisesPerThread, true);
    $newPromises = [];
    foreach ($threads as $threadPromises) {
        $newPromises[] = chain($threadPromises);
    }
    return all($newPromises)
        ->then(function (array $chainResultsContainer) {
            return array_reduce($chainResultsContainer, function ($carry, $chainResults) {
                $carry += $chainResults;
                return $carry;
            }, []);
        });
}
