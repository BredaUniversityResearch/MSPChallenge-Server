<?php

namespace App\Entity;

use App\Domain\Helper\Util;
use App\Entity\Mapping\Plurals;
use App\Entity\Trait\EntityOriginTrait;
use ReflectionClass;

abstract class EntityBase
{
    use EntityOriginTrait;

    public function getPlurals(): Plurals
    {
        $reflectionClass = new ReflectionClass(static::class);
        $attribute = Util::getClassAttribute($reflectionClass, Plurals::class);
        // If no Plurals attribute is found, return a default Plurals object
        $attribute ??= new Plurals($reflectionClass->getShortName(), $reflectionClass->getShortName());
        return $attribute;
    }
}
