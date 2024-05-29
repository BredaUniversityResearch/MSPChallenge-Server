<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;

class SeasonalClosurePolicyData extends PolicyBasePolicyData
{
    public function __construct()
    {
        parent::__construct(PolicyTypeName::SEASONAL_CLOSURE->value);
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyTypeName::SEASONAL_CLOSURE, PolicyDataMetaName::TYPE_NAME->value);
        parent::setUpProperties($properties, $ownerSchema);

        // items
        $itemsSchema = Schema::arr();
        $itemSchema = Schema::object();
        $itemSchema->allOf = [
            // define the allowed filters here
            FleetFilterPolicyData::schema(),
            ScheduleFilterPolicyData::schema()
        ];
        $itemsSchema->items = $itemSchema;
        $properties->items = $itemsSchema;
    }
}
