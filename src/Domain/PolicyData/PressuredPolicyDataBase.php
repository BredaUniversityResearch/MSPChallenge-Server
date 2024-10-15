<?php

namespace App\Domain\PolicyData;

use ReflectionException;
use Swaggest\JsonSchema\Schema;

abstract class PressuredPolicyDataBase extends ItemsPolicyDataBase
{
    public float $pressure = 0.0;

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        parent::setUpProperties($properties, $ownerSchema);
        $properties->pressure = Schema::number();
        $properties->pressure->minimum = 0;
        $properties->pressure->maximum = 1;
    }
}
