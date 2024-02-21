<?php

namespace App\Domain\Common;

use Closure;
use React\Promise\CancellablePromiseInterface;
use React\Promise\ExtendedPromiseInterface;
class ToPromiseFunction
{
    private Closure $function;

    public function __construct(Closure $function)
    {
        $this->function = $function;
    }

    public function __invoke(): ExtendedPromiseInterface&CancellablePromiseInterface
    {
        return ($this->function)();
    }
}
