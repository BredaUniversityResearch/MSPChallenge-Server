<?php

namespace App\Domain\Common;

class Context
{
    private array $path = [];

    public static function root(): self
    {
        $context = new self();
        return $context->enter('root');
    }

    public function enter(string $name): self
    {
        $copy = $this->copy();
        $copy->path[] = $name;
        return $copy;
    }

    public function getPath(): string
    {
        return implode('.', $this->path);
    }

    private function copy(): self
    {
        $newContext = new self();
        $newContext->path = $this->path;
        return $newContext;
    }
}
