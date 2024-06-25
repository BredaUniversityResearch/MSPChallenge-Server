<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use App\Domain\Helper\Util;
use App\Domain\Log\LogContainerInterface;
use App\Domain\Log\LogContainerTrait;
use ReflectionException;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

abstract class PolicyDataBase extends ClassStructure implements LogContainerInterface
{
    use LogContainerTrait;

    public string $type;
    public float $pressure = 0.0;

    abstract public function getPolicyTypeName(): PolicyTypeName;

    abstract public function __construct(); // enforce the constructor to have no arguments

    public function matchFiltersOn(object $otherItem): ?bool
    {
        return null;
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyGroup::POLICY, PolicyDataSchemaMetaName::POLICY_GROUP->value);
        $ownerSchema->type = 'object';
        // by default, we require all properties including the ones from the child classes
        $ownerSchema->required = Util::getClassPropertyNames(
            get_called_class(),
            \ReflectionProperty::IS_PUBLIC,
            __CLASS__
        );
        $ownerSchema->additionalProperties = true; // we allow additional properties
        $properties->type = Schema::string();
        $properties->pressure = Schema::number();
        $properties->pressure->minimum = 0;
        $properties->pressure->maximum = 1;
    }
}
