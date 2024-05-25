<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

class BufferZonePolicyData extends ClassStructure
{
    const DEFAULT_VALUE_RADIUS = 40000.0;

    public float $radius = self::DEFAULT_VALUE_RADIUS;

    public function __construct(float $radius = self::DEFAULT_VALUE_RADIUS)
    {
        $this->radius = $radius;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, $ownerSchema)
    {
        $ownerSchema->addMeta(PolicyGroup::POLICY, PolicyDataMetaName::GROUP->value);
        $ownerSchema->addMeta(PolicyTypeName::BUFFER_ZONE, PolicyDataMetaName::TYPE_NAME->value);
        $ownerSchema->type = 'object';
        $radiusSchema = Schema::number();
        $radiusSchema->default = self::DEFAULT_VALUE_RADIUS;
        $properties->radius = $radiusSchema;
        $ownerSchema->required = ['radius'];
    }
}