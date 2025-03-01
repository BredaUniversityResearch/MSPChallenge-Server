<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;

class FishingEffortPolicyData extends PolicyDataBase
{
    public function __construct()
    {
        $this->policy_type = PolicyTypeName::FISHING_EFFORT->value;
    }

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::FISHING_EFFORT;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema
            ->addMeta(PolicyTypeName::FISHING_EFFORT, PolicyDataSchemaMetaName::POLICY_TYPE_NAME->value)
            ->addMeta(PolicyTarget::PLAN, PolicyDataSchemaMetaName::POLICY_TARGET->value);
        parent::setUpProperties($properties, $ownerSchema);
    }
}
