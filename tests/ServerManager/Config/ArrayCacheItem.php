<?php

namespace App\Tests\ServerManager\Config;

use Psr\Cache\CacheItemInterface;

/**
 * A cache item for ArrayCachePool.
 */
final class ArrayCacheItem implements CacheItemInterface
{
    public function __construct(private readonly string $key, private mixed $value, private bool $hit)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->hit ? $this->value : null;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        return $this;
    }
}
