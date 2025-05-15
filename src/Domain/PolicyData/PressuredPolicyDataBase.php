<?php

namespace App\Domain\PolicyData;

use ReflectionException;
use Swaggest\JsonSchema\Context;
use Swaggest\JsonSchema\Schema;

abstract class PressuredPolicyDataBase extends ItemsPolicyDataBase
{
    const DEFAULT_VALUE_PRESSURE = 0.0;

    public float $pressure = self::DEFAULT_VALUE_PRESSURE;

    public static function import($data, Context $options = null)
    {
        $data->pressure ??= self::DEFAULT_VALUE_PRESSURE;
        return parent::import($data, $options);
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        parent::setUpProperties($properties, $ownerSchema);
        $properties->pressure = Schema::number();
        $properties->pressure->default = self::DEFAULT_VALUE_PRESSURE;
        $properties->pressure->minimum = 0;
        $properties->pressure->maximum = 1;
    }
}
