<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyTypeName;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

class EcoGearPolicyData extends ClassStructure
{
    /**
     * @var bool[]
     */
    public $per_country;

    /**
     * @param bool[] $per_country
     */
    public function __construct(array $per_country)
    {
        $this->per_country = $per_country;
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema)
    {
        $ownerSchema->addMeta(PolicyGroup::POLICY, PolicyDataMetaName::GROUP->value);
        $ownerSchema->addMeta(PolicyTypeName::ECO_GEAR, PolicyDataMetaName::TYPE_NAME->value);
        $ownerSchema->type = 'object';
        $perCountrySchema = Schema::object();
        $perCountrySchema->additionalProperties = false;
        $booleanSchema = Schema::boolean();
        $perCountrySchema->setPatternProperty('^[0-9]+$', $booleanSchema);
        $properties->per_country = $perCountrySchema;
        $ownerSchema->required = ['per_country'];
    }
}
