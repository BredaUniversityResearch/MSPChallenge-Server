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

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::SEASONAL_CLOSURE;
    }

    protected function getItemSchema(): Schema
    {
        $schema = Schema::object();
        $schema->allOf = [
                // define the allowed filters here
                FleetFilterPolicyData::schema(),
                ScheduleFilterPolicyData::schema()
            ];
        return $schema;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyTypeName::SEASONAL_CLOSURE, PolicyDataMetaName::TYPE_NAME->value);
        parent::setUpProperties($properties, $ownerSchema);
    }
}
