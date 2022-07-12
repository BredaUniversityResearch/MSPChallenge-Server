<?php

namespace App\Domain\Common;

use Closure;
use React\Promise\PromiseInterface;

class ToPromiseFunction
{
    private Closure $function;

    public function __construct(Closure $function)
    {
        $this->function = $function;
    }

    public function __invoke(): PromiseInterface
    {
        return ($this->function)();
    }
}
