<?php

namespace App\Domain\Common;

use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/***
 * This name convertor will first call the preparer function, then check the mapping array and
 * finally call the default convertor
 */
class CustomMappingNameConvertor implements NameConverterInterface
{
    /** @var array<string, string> $customMapping */
    private array $customMapping= [];

    private ?NameConverterInterface $defaultConverter = null;

    /** @param array<string, string> $customMapping */
    public function __construct(
        array $customMapping = [],
        ?NameConverterInterface $defaultConverter = null
    ) {
        $this->customMapping = $customMapping;
        $this->defaultConverter = $defaultConverter;
    }

    public function getCustomMapping(): array
    {
        return $this->customMapping;
    }

    public function setCustomMapping(array $customMapping): static
    {
        $this->customMapping = $customMapping;
        return $this;
    }

    public function getDefaultConverter(): ?NameConverterInterface
    {
        return $this->defaultConverter;
    }

    public function setDefaultConverter(?NameConverterInterface $defaultConverter): static
    {
        $this->defaultConverter = $defaultConverter;
        return $this;
    }

    public function normalize(string $propertyName): string
    {
        if (isset($this->customMapping[$propertyName])) {
            return $this->customMapping[$propertyName];
        }
        return $this->defaultConverter?->normalize($propertyName);
    }

    public function denormalize(string $propertyName): string
    {
        $mapping = array_flip($this->customMapping);
        if (isset($mapping[$propertyName])) {
            return $mapping[$propertyName];
        }
        return $this->defaultConverter?->denormalize($propertyName);
    }
}
