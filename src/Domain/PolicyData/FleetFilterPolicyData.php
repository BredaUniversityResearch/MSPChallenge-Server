<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

class FleetFilterPolicyData extends ClassStructure
{
    const DEFAULT_VALUE_FLEETS = 0;

    public int $fleets = self::DEFAULT_VALUE_FLEETS;

    public function __construct(int $fleets = self::DEFAULT_VALUE_FLEETS)
    {
        $this->fleets = $fleets;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyGroup::FILTER, PolicyDataMetaName::GROUP->value);
        $ownerSchema->addMeta(PolicyFilterTypeName::FLEET, PolicyDataMetaName::TYPE_NAME->value);
        $ownerSchema->type = 'object';
        $fleetsSchema = Schema::integer();
        $fleetsSchema->default = self::DEFAULT_VALUE_FLEETS;
        $properties->fleets = $fleetsSchema;
        $ownerSchema->required = ['fleets'];
    }
}
