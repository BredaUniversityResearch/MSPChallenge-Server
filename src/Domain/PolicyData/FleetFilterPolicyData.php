<?php

namespace App\Domain\PolicyData;

use App\Domain\API\v1\Game;
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
            return true; // no "filter", so just match.
        }
        if (!is_array($otherItem->fleets)) {
            return false; // there seems to be a setup for a filter, but the value is not an array, so do not match
        }
        return empty(array_diff($otherItem->fleets, $this->fleets ?? []));
    }

    /**
     * @inheritdoc
     */
    public static function setUpProperties($properties, Schema $ownerSchema): void
    {
        parent::setUpProperties($properties, $ownerSchema);
        $ownerSchema->addMeta(PolicyFilterTypeName::FLEET, PolicyDataSchemaMetaName::POLICY_TYPE_NAME->value);
        $fleetsSchema = Schema::arr()
            ->addMeta(function (int $gameSessionId) {
                $game = new Game();
                $game->setGameSessionId($gameSessionId);
                $dataModel = $game->getGameConfigValues();
                $gearTypes = $dataModel['policy_settings']['fishing']['fleet_info']['gear_types'] ?? [];
                return collect($dataModel['policy_settings']['fishing']['fleet_info']['fleets'] ?? [])
                    ->map(fn($f) => $gearTypes[$f['gear_type']].' fleets')->toArray();
            }, PolicyDataSchemaMetaName::FIELD_ON_INPUT_CHOICES->value)
            ->addMeta(
                'Enter one of the following fleet (as integer)',
                PolicyDataSchemaMetaName::FIELD_ON_INPUT_DESCRIPTION->value
            );
        $fleetsSchema->items = Schema::integer();
        $properties->fleets = $fleetsSchema;
    }
}
