<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Structure\ClassStructure;

class FleetFilterPolicyData extends ClassStructure
{
    /**
     * @var int[]
     */
    public array $fleets = [];

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        $ownerSchema->addMeta(PolicyGroup::FILTER, PolicyDataMetaName::GROUP->value);
        $ownerSchema->addMeta(PolicyFilterTypeName::FLEET, PolicyDataMetaName::TYPE_NAME->value);
        $ownerSchema->type = 'object';
        $fleetsSchema = Schema::arr()
            ->addMeta(true, PolicyDataMetaName::ON_INPUT_SHOW_LAYER_TYPES->value)
            ->addMeta('Enter one of the following fleet ids', PolicyDataMetaName::ON_INPUT_DESCRIPTION->value);
        $fleetsSchema->items = Schema::integer();
        $properties->fleets = $fleetsSchema;
        $ownerSchema->required = ['fleets'];
    }
}
