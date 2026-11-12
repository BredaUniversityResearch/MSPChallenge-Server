<?php

namespace App\Tests\ServerManager\Config;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A cache pool in memory that counts what is done with it, to see in a test whether a cache was used. Several loaders
 * can share one pool, like processes share the cache pool of the application.
 */
final class ArrayCachePool implements CacheItemPoolInterface
{
    public int $saves = 0;
    public int $reads = 0;
    /** Make every use of the pool fail, to see that a cache that does not work does no harm. */
    public bool $failing = false;
    /** @var array<string, mixed> */
    private array $values = [];

    public function getItem(string $key): CacheItemInterface
    {
        $this->failIfAsked();
        $this->reads++;
        return new ArrayCacheItem($key, $this->values[$key] ?? null, array_key_exists($key, $this->values));
    }

    /**
     * @param string[] $keys
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function clear(): bool
    {
        $this->values = [];
        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->values[$key]);
        return true;
    }

    /**
     * @param string[] $keys
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->values[$key]);
        }
        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->failIfAsked();
        $this->saves++;
        $this->values[$item->getKey()] = $item->get();
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }

    private function failIfAsked(): void
    {
        if ($this->failing) {
            throw new \RuntimeException('the cache does not work');
        }
    }
}
