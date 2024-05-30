<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;

class BufferZonePolicyData extends PolicyBasePolicyData
{
    const DEFAULT_VALUE_RADIUS = 40000.0;

    public float $radius = self::DEFAULT_VALUE_RADIUS;

    public function __construct(float $radius = self::DEFAULT_VALUE_RADIUS)
    {
        parent::__construct(PolicyTypeName::BUFFER_ZONE->value);
        $this->radius = $radius;
    }

    public function getPolicyTypeName(): PolicyTypeName
    {
        return PolicyTypeName::BUFFER_ZONE;
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
    public static function setUpProperties($properties, $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyTypeName::BUFFER_ZONE, PolicyDataMetaName::TYPE_NAME->value);
        parent::setUpProperties($properties, $ownerSchema);

        // radius
        $radiusSchema = Schema::number();
        $radiusSchema->default = self::DEFAULT_VALUE_RADIUS;
        $properties->radius = $radiusSchema;
    }
}
