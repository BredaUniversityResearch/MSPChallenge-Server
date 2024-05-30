<?php

namespace App\Domain\PolicyData;

use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use Swaggest\JsonSchema\Schema;

class FleetFilterPolicyData extends FilterBasePolicyData
{
    /**
     * @var int[]
     */
    public array $fleets = [];

    /**
     * @param int[] $fleets
     */
    public function __construct(array $fleets = [])
    {
        $this->fleets = $fleets;
    }

    public function getFilterTypeName(): PolicyFilterTypeName
    {
        return PolicyFilterTypeName::FLEET;
    }

    public function match(object $otherItem): bool
    {
        if (property_exists($otherItem, 'fleets') === false) {
            return false;
        }
        if (!is_array($otherItem->fleets)) {
            return false;
        }
        return empty(array_diff($otherItem->fleets, $this->fleets ?? []));
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        parent::setUpProperties($properties, $ownerSchema);
        $ownerSchema->addMeta(PolicyFilterTypeName::FLEET, PolicyDataMetaName::TYPE_NAME->value);
        $fleetsSchema = Schema::arr()
            ->addMeta(true, PolicyDataMetaName::ON_INPUT_SHOW_LAYER_TYPES->value)
            ->addMeta('Enter one of the following fleet ids', PolicyDataMetaName::ON_INPUT_DESCRIPTION->value);
        $fleetsSchema->items = Schema::integer();
        $properties->fleets = $fleetsSchema;
    }
}
