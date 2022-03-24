<?php

namespace App\Domain\Common;

class ObjectMethod
{
    private object $instance;
    private string $method;

    public function __construct(object $instance, string $method)
    {
        $this->instance = $instance;
        $this->method = $method;
        assert(method_exists($instance, $method));
    }

    public function getInstance(): object
    {
        return $this->instance;
    }

    public function setInstance(object $instance): void
    {
        $this->instance = $instance;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function __invoke(...$args)
    {
        return call_user_func_array([$this->instance, $this->method], ...$args);
    }
}
