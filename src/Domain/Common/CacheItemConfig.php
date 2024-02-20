<?php

namespace App\Domain\Common;

class CacheItemConfig
{
    public function __construct(
        private string $key,
        private ?int $lifeTime = null
    ) {
        $this->setKey($key); // fixes the key if it contains reserved characters
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey($key): self
    {
        // make sure the key does not contain reserved characters "{}()/\@:" by replace them with ~
        $key = str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], '~', $key);
        $this->key = $key;
        return $this;
    }

    public function getLifeTime(): ?int
    {
        return $this->lifeTime;
    }

    public function setLifeTime($lifeTime): self
    {
        $this->lifeTime = $lifeTime;
        return $this;
    }
}