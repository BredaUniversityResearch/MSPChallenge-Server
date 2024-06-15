<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;

class BufferZonePolicyData extends PolicyDataBase
{
    const DEFAULT_VALUE_RADIUS = 40000.0;

    public float $radius = self::DEFAULT_VALUE_RADIUS;

    public function __construct()
    {
        $this->type = PolicyTypeName::BUFFER_ZONE->value;
    }

    public function getRadius(): float
    {
        return $this->radius;
    }

    public function setRadius(float $radius): self
    {
        $this->radius = $radius;
        return $this;
    }

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::BUFFER_ZONE;
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
    public static function setUpProperties($properties, $ownerSchema): void
    {
        $ownerSchema
            ->addMeta(PolicyTypeName::BUFFER_ZONE, PolicyDataSchemaMetaName::POLICY_TYPE_NAME->value)
            ->addMeta(PolicyTarget::GEOMETRY, PolicyDataSchemaMetaName::POLICY_TARGET->value);
        parent::setUpProperties($properties, $ownerSchema);

        // radius
        $radiusSchema = Schema::number();
        $radiusSchema->default = self::DEFAULT_VALUE_RADIUS;
        $properties->radius = $radiusSchema;
    }
}
