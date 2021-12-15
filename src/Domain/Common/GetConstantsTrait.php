<?php

namespace App\Domain\Common;

use ReflectionClass;

trait GetConstantsTrait
{
    public function getConstants()
    {
        $class = new ReflectionClass(__CLASS__);
        return $class->getConstants();
    }
}
