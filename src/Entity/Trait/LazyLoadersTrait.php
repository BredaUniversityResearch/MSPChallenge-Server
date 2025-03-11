<?php

namespace App\Entity\Trait;

use Closure;

trait LazyLoadersTrait
{
    /** @var Closure[] $lazyLoaders */
    private array $lazyLoaders = [];

    public function hasLazyLoader(string $propertyName): bool
    {
        return array_key_exists($propertyName, $this->lazyLoaders);
    }

    public function getLazyLoader(string $propertyName): ?Closure
    {
        return $this->lazyLoaders[$propertyName] ?? null;
    }

    public function setLazyLoader(string $propertyName, Closure $loader): self
    {
        $this->lazyLoaders[$propertyName] = $loader;
        return $this;
    }

    public function __serialize(): array
    {
        $data = get_object_vars($this);
        unset($data['lazyLoaders']);
        return $data;
    }
}
