<?php

namespace App\Domain\Common;

use ReflectionClass;

trait GetConstantsTrait
{
    public function getConstants()
    {
        $class = new ReflectionClass(get_called_class());
        return $class->getConstants();
    }
}
