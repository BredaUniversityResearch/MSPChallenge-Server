<?php

namespace App;

use App\Domain\Common\ToPromiseFunction;
use Closure;
use Doctrine\DBAL\Exception\DriverException;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Symfony\Component\Yaml\Yaml;
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
        function ($reason) {
            if (is_string($reason)) {
                die($reason);
            }
            if ($reason instanceof \Throwable) {
                die(
                    $reason->getMessage() . PHP_EOL . 'in ' .
                    $reason->getFile() . '@' . $reason->getLine() . PHP_EOL .
                    $reason->getTraceAsString()
                );
            }
            die('error, reason: ' . print_r($reason, true));
        }
    );
}

function tpf(Closure $function): ToPromiseFunction
{
    return new ToPromiseFunction($function);
}

/**
 * @param ToPromiseFunction[] $toPromiseFunctions
 */
function chain(array $toPromiseFunctions): ?PromiseInterface
{
    $deferred = new Deferred();
    if (false === $toPromiseFunction = reset($toPromiseFunctions)) {
        return resolveOnFutureTick($deferred, [])->promise();
    }
    $results = [];

    // execute first promise
    $promise = $toPromiseFunction();

    $nextKey = key($toPromiseFunctions);
    while ($nextToPromiseFunction = next($toPromiseFunctions)) {
        $promise = $promise->then(function ($result) use ($nextToPromiseFunction, &$results, $nextKey) {
             $results[$nextKey] = $result;

             // execute next promise
             return $nextToPromiseFunction();
        });
        $nextKey = key($toPromiseFunctions);
    }
    $promise
        ->then(
            function ($result) use ($deferred, &$results, $nextKey) {
                $results[$nextKey] = $result;
                $deferred->resolve($results);
            },
            function ($reason) use ($deferred) {
                if ($reason instanceof DriverException) {
                    /** @var DriverException $reason */
                    $deferred->reject(new \Exception(
                        $reason->getMessage() . PHP_EOL .
                            'query: ' . PHP_EOL . $reason->getQuery()->getSQL() . PHP_EOL .
                            'parameters: ' . PHP_EOL . Yaml::dump($reason->getQuery()->getParams()),
                        $reason->getCode(),
                        $reason
                    ));
                }
                $deferred->reject($reason);
            }
        );
    return $deferred->promise();
}

/**
 * @param ToPromiseFunction[] $toPromiseFunctions
 */
function parallel(array $toPromiseFunctions, int $numThreads)
{
    $numThreads = max(1, $numThreads); // should be at least one
    if (empty($toPromiseFunctions)) {
        return resolveOnFutureTick(new Deferred(), [])->promise();
    }

    $numPromisesPerThread = count($toPromiseFunctions) / $numThreads;
    $threads = array_chunk($toPromiseFunctions, $numPromisesPerThread, true);
    $newPromises = [];
    foreach ($threads as $toPromiseFunctionsPerThread) {
        $newPromises[] = chain($toPromiseFunctionsPerThread);
    }
    return all($newPromises)
        ->then(function (array $chainResultsContainer) {
            return array_reduce($chainResultsContainer, function ($carry, $chainResults) {
                $carry += $chainResults;
                return $carry;
            }, []);
        });
}
