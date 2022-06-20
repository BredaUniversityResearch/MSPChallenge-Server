<?php

namespace App\Domain\Common;

use ReflectionClass;

trait GetConstantsTrait
{
    public static function getConstants(): array
    {
        $class = new ReflectionClass(get_called_class());
        return $class->getConstants();
    }
}
