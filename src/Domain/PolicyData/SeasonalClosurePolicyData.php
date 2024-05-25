<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

class SeasonalClosurePolicyData extends ClassStructure
{
    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyGroup::POLICY, PolicyDataMetaName::GROUP->value);
        $ownerSchema->addMeta(PolicyTypeName::SEASONAL_CLOSURE, PolicyDataMetaName::TYPE_NAME->value);
        $ownerSchema->type = 'object';
        $ownerSchema->additionalProperties = true;
    }
}
