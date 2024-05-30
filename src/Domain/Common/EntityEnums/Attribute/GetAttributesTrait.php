<?php

namespace App\Domain\Common\EntityEnums\Attribute;

use ReflectionClassConstant;

trait GetAttributesTrait
{
    public static function getDescription(self $enum): string
    {
        $ref = new ReflectionClassConstant(self::class, $enum->name);
        $classAttributes = $ref->getAttributes(Description::class);
        if (count($classAttributes) === 0) {
            return $enum->value; // fall-back to enum value if there is no description
        }
        return $classAttributes[0]->newInstance()->description;
    }
}