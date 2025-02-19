<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;

class SandExtractionPolicyData extends PolicyDataBase
{
    public function __construct()
    {
        $this->policy_type = PolicyTypeName::SAND_EXTRACTION->value;
    }

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::SAND_EXTRACTION;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema
            ->addMeta(PolicyTypeName::SAND_EXTRACTION, PolicyDataSchemaMetaName::POLICY_TYPE_NAME->value)
            ->addMeta(PolicyTarget::PLAN, PolicyDataSchemaMetaName::POLICY_TARGET->value);
        parent::setUpProperties($properties, $ownerSchema);
    }
}
