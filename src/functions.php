<?php

namespace App;

use App\Domain\Common\ToPromiseFunction;
use Closure;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Query\QueryBuilder;
use Drift\DBAL\Connection;
use Exception;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Timer;
use React\Promise\Timer\TimeoutException;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use function React\Promise\all;

function query(Connection $connection, QueryBuilder $qb): Promise
{
    return $connection->query($qb);
}

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

function assertFulfilled(ExtendedPromiseInterface $promise, ?Closure $onFullfulled = null): void
{
    $promise->done(
        $onFullfulled,
        function ($reason) {
            if (is_string($reason)) {
                die($reason);
            }
            if ($reason instanceof Throwable) {
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
function chain(array $toPromiseFunctions): Promise
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
function parallel(array $toPromiseFunctions, ?int $numThreads = null): Promise
{
    // default is a thread per task
    if (null === $numThreads) {
        $numThreads = count($toPromiseFunctions);
    }
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

/**
 * @note (MH) copied and modified from composer package: clue/block-react
 * see: https://github.com/clue/reactphp-block/blob/master/src/functions.php
 * This version prevents the websocket server to be stopped
 *
 * Block waiting for the given `$promise` to be fulfilled.
 *
 * ```php
 * $result = await($promise, $loop);
 * ```
 *
 * This function will only return after the given `$promise` has settled, i.e.
 * either fulfilled or rejected. In the meantime, the event loop will run any
 * events attached to the same loop until the promise settles.
 *
 * Once the promise is fulfilled, this function will return whatever the promise
 * resolved to.
 *
 * Once the promise is rejected, this will throw whatever the promise rejected
 * with. If the promise did not reject with an `Exception`, then this function
 * will throw an `UnexpectedValueException` instead.
 *
 * ```php
 * try {
 *     $result = await($promise, $loop);
 *     // promise successfully fulfilled with $result
 *     echo 'Result: ' . $result;
 * } catch (Exception $exception) {
 *     // promise rejected with $exception
 *     echo 'ERROR: ' . $exception->getMessage();
 * }
 * ```
 *
 * This function takes an optional `LoopInterface|null $loop` parameter that can be used to
 * pass the event loop instance to use. You can use a `null` value here in order to
 * use the [default loop](https://github.com/reactphp/event-loop#loop). This value
 * SHOULD NOT be given unless you're sure you want to explicitly use a given event
 * loop instance.
 *
 * If no `$timeout` argument is given and the promise stays pending, then this
 * will potentially wait/block forever until the promise is settled. To avoid
 * this, API authors creating promises are expected to provide means to
 * configure a timeout for the promise instead. For more details, see also the
 * [`timeout()` function](https://github.com/reactphp/promise-timer#timeout).
 *
 * If the deprecated `$timeout` argument is given and the promise is still pending once the
 * timeout triggers, this will `cancel()` the promise and throw a `TimeoutException`.
 * This implies that if you pass a really small (or negative) value, it will still
 * start a timer and will thus trigger at the earliest possible time in the future.
 *
 * @param PromiseInterface $promise
 * @param ?LoopInterface   $loop
 * @param ?float           $timeout [deprecated] (optional) maximum timeout in seconds or null=wait forever
 * @return mixed returns whatever the promise resolves to
 * @throws Exception when the promise is rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function await(PromiseInterface $promise, LoopInterface $loop = null, $timeout = null)
{
    $wait = true;
    $resolved = null;
    $exception = null;
    $rejected = false;
    $loop = $loop ?: Loop::get();

    if ($timeout !== null) {
        $promise = Timer\timeout($promise, $timeout, $loop);
    }

    $promise->then(
        function ($c) use (&$resolved, &$wait, $loop) {
            $resolved = $c;
            $wait = false;
            $loop->stop();
        },
        function ($error) use (&$exception, &$rejected, &$wait, $loop) {
            $exception = $error;
            $rejected = true;
            $wait = false;
            $loop->stop();
        }
    );

    // Explicitly overwrite argument with null value. This ensure that this
    // argument does not show up in the stack trace in PHP 7+ only.
    $promise = null;

    while ($wait) {
        $loop->run();
    }

    // @hack (MH) fail-safe
    // in-case await was called inside the websocket server instance, which should not happen normally
    // so now that we exited the "awaiting" loop because "stop" was called, and let's re-run the websocket server loop,
    //  since that loop should not be stopped by await.
    if (defined('WSS')) {
        $prop = new \ReflectionProperty(get_class($loop), 'running');
        $prop->setAccessible(true);
        $prop->setValue($loop, true);
        $prop->setAccessible(false);
    }

    if ($rejected) {
        if (!$exception instanceof Throwable) {
            $exception = new \UnexpectedValueException(
                'Promise rejected with unexpected value of type ' .
                    (is_object($exception) ? get_class($exception) : gettype($exception))
            );
        } elseif (!$exception instanceof \Exception) { // so it is a Throwable but not an Exception
            $exception = new \UnexpectedValueException(
                'Promise rejected with unexpected ' . get_class($exception) . ': ' . $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }

        throw $exception;
    }

    return $resolved;
}
