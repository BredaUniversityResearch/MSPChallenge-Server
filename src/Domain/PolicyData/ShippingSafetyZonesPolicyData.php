<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;

class ShippingSafetyZonesPolicyData extends PolicyDataBase
{
    public function __construct()
    {
        $this->policy_type = PolicyTypeName::SHIPPING_SAFETY_ZONES->value;
    }

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::SHIPPING_SAFETY_ZONES;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema
            ->addMeta(PolicyTypeName::SHIPPING_SAFETY_ZONES, PolicyDataSchemaMetaName::POLICY_TYPE_NAME->value)
            ->addMeta(PolicyTarget::PLAN, PolicyDataSchemaMetaName::POLICY_TARGET->value);
        parent::setUpProperties($properties, $ownerSchema);
    }
}
