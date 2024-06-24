<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Constraint\Properties;
use Swaggest\JsonSchema\Schema;

class EcoGearPolicyData extends ItemsPolicyDataBase
{
    public function __construct()
    {
        $this->type = PolicyTypeName::ECO_GEAR->value;
    }

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::ECO_GEAR;
    }

    public function getItemSchema(): Schema
    {
        $schema = Schema::object();
        $schema->properties ??= new Properties();
        $schema->properties->enabled = Schema::boolean();
        $schema->required = ['enabled'];
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
        $ownerSchema
            ->addMeta(PolicyTypeName::ECO_GEAR, PolicyDataSchemaMetaName::POLICY_TYPE_NAME->value)
            ->addMeta(PolicyTarget::PLAN, PolicyDataSchemaMetaName::POLICY_TARGET->value);
        parent::setUpProperties($properties, $ownerSchema);
    }
}
