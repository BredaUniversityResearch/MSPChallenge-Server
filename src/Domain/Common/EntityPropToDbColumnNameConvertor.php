<?php

namespace App\Domain\Common;

use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class EntityPropToDbColumnNameConvertor extends CamelCaseToSnakeCaseNameConverter
{
    public function __construct(
        private readonly ?array $attributes = null,
        /** @var array<string, string> */
        private readonly array $customNameMapping = []
    ) {
        parent::__construct($this->attributes);
    }

    public function normalize(string $propertyName): string
    {
        if (isset($this->customNameMapping[$propertyName])) {
            return $this->customNameMapping[$propertyName];
        }
        return parent::normalize($propertyName);
    }

    public function denormalize(string $propertyName): string
    {
        $mapping = array_flip($this->customNameMapping);
        if (isset($mapping[$propertyName])) {
            return $mapping[$propertyName];
        }
        return parent::denormalize($propertyName);
    }
}
