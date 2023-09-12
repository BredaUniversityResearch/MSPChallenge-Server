<?php

namespace App\Domain\Common;

use Closure;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Promise\RejectedPromise;

class ToPromiseFunction
{
    private Closure $function;

    public function __construct(Closure $function)
    {
        $this->function = $function;
    }

    public function __invoke(): Promise|FulfilledPromise|RejectedPromise
    {
        return ($this->function)();
    }
}
