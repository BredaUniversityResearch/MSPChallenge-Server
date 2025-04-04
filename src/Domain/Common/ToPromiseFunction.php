<?php

namespace App\Domain\Common;

use Closure;
use React\Promise\CancellablePromiseInterface;
use React\Promise\ExtendedPromiseInterface;

class ToPromiseFunction
{
    private ?Context $context = null;
    private Closure $function;

    public function __construct(Closure $function)
    {
        $this->function = $function;
    }

    public function __invoke(): ExtendedPromiseInterface&CancellablePromiseInterface
    {
        return ($this->function)($this->getContext());
    }

    public function getContext(): ?Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }
}
