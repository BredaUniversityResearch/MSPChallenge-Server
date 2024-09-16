<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;

class SeasonalClosurePolicyData extends ItemsPolicyDataBase
{
    public function __construct()
    {
        $this->policy_type = PolicyTypeName::SEASONAL_CLOSURE->value;
    }

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::SEASONAL_CLOSURE;
    }

    public function getItemSchema(): Schema
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
        $ownerSchema
            ->addMeta(PolicyTypeName::SEASONAL_CLOSURE, PolicyDataSchemaMetaName::POLICY_TYPE_NAME->value)
            ->addMeta(PolicyTarget::GEOMETRY, PolicyDataSchemaMetaName::POLICY_TARGET->value);
        parent::setUpProperties($properties, $ownerSchema);
    }
}
