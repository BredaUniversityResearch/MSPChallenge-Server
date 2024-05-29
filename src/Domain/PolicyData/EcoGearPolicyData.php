<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;

class EcoGearPolicyData extends PolicyBasePolicyData
{
    public function __construct()
    {
        parent::__construct(PolicyTypeName::ECO_GEAR->value);
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyTypeName::ECO_GEAR, PolicyDataMetaName::TYPE_NAME->value);
        parent::setUpProperties($properties, $ownerSchema);

        // items
        $itemsSchema = Schema::arr();
        $itemSchema = Schema::object();
        $itemSchema->properties->enabled = Schema::boolean();
        $itemSchema->allOf = [
            // define the allowed filters here
            FleetFilterPolicyData::schema()
        ];
        $itemsSchema->items = $itemSchema;
        $properties->items = $itemsSchema;
    }
}
