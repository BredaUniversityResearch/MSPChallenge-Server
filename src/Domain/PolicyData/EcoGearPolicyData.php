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

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::ECO_GEAR;
    }

    protected function getItemSchema(): Schema
    {
        $schema = Schema::object();
        $schema->properties->enabled = Schema::boolean();
        $schema->allOf = [
                // define the allowed filters here
                FleetFilterPolicyData::schema()
            ];
        return $schema;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyTypeName::ECO_GEAR, PolicyDataMetaName::TYPE_NAME->value);
        parent::setUpProperties($properties, $ownerSchema);
    }
}
