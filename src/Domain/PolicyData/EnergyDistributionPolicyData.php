<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;

class EnergyDistributionPolicyData extends PolicyDataBase
{
    public function __construct()
    {
        $this->policy_type = PolicyTypeName::ENERGY_DISTRIBUTION->value;
    }

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::ENERGY_DISTRIBUTION;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema
            ->addMeta(PolicyTypeName::ENERGY_DISTRIBUTION, PolicyDataSchemaMetaName::POLICY_TYPE_NAME->value)
            ->addMeta(PolicyTarget::PLAN, PolicyDataSchemaMetaName::POLICY_TARGET->value);
        parent::setUpProperties($properties, $ownerSchema);
    }
}
