<?php

namespace App\Domain\Common;

use Closure;
use React\Promise\PromiseInterface;

class ToPromiseFunction
{
    private ?Context $context = null;
    private Closure $function;

    public function __construct(Closure $function)
    {
        $this->function = $function;
    }

    public function __invoke(): PromiseInterface
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
