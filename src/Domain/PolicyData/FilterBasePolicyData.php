<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use App\Domain\Helper\Util;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

abstract class FilterBasePolicyData extends ClassStructure
{
    abstract public function getFilterTypeName(): PolicyFilterTypeName;
    abstract public function match(object $otherItem): bool;

    /**
     * @inheritdoc
     * @throws \ReflectionException
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyGroup::FILTER, PolicyDataSchemaMetaName::POLICY_GROUP->value);
        $ownerSchema->type = 'object';
        // by default, we require all properties including the ones from the child classes
        $ownerSchema->required = Util::getClassPropertyNames(
            get_called_class(),
            \ReflectionProperty::IS_PUBLIC,
            __CLASS__
        );
    }
}
