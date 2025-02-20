<?php

namespace App\Domain\Services;

use App\Domain\Common\InternalSimulationName;
use App\Entity\ServerManager\GameList;
use App\VersionsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

readonly class SimulationHelper
{
    public function __construct(
        private EntityManagerInterface $em,
        private VersionsProvider       $provider
    ) {
    }

    /**
     * @return array<'CEL'|'MEL'|'SEL', mixed> $internalSims all internal simulations,
     *   the key being the name the value being the version string.
     * @throws Exception
     */
    public function getInternalSims(int $sessionId, ?array $dataModel = null): array
    {
        $dataModel ??= $this->getDataModel($sessionId);
        // filter possible internal simulations with the ones present in the config
        $simNames = array_keys(array_intersect_key(
            array_flip(array_map(
                fn(InternalSimulationName $e) => $e->value,
                InternalSimulationName::cases()
            )),
            $dataModel
        ));
        // get the versions of the sims
        $versions = $this->getConfiguredSimulationTypes(
            $sessionId,
            $dataModel
        );
        return array_intersect_key($versions, array_flip($simNames));
    }

    /**
     * @throws Exception
     */
    public function getConfiguredSimulationTypes(int $sessionId, ?array $dataModel = null): array
    {
        $result = [];
        $dataModel ??= $this->getDataModel($sessionId);
        $possibleSims = $this->provider->getComponentsVersions();
        foreach ($possibleSims as $possibleSim => $possibleSimVersion) {
            if (array_key_exists($possibleSim, $dataModel) && is_array($dataModel[$possibleSim])) {
                $versionString = $possibleSimVersion;
                if (array_key_exists("force_version", $dataModel[$possibleSim])) {
                    $versionString = $dataModel[$possibleSim]["force_version"];
                }
                $result[$possibleSim] = $versionString;
            }
        }
        return $result;
    }

    /**
     * phpcs:ignore Generic.Files.LineLength.TooLong
     * @return array{restrictions: array, plans: array, policy_settings: array, stakeholder_pressure_settings: array, dependencies: array, CEL: ?array, REL: ?array, SEL: ?array{heatmap_settings: array, shipping_lane_point_merge_distance: int, shipping_lane_subdivide_distance: int, shipping_lane_implicit_distance_limit: int, maintenance_destinations: array, output_configuration: array}, MEL: ?array{x_min?: int, x_max?: int, y_min?: int, y_max?: int, cellsize: int, columns: int, rows: int, pressures: array{name: string, layers: array, policy_filters?: array{fleets: array}}, fishing: array}, meta: array{array{layer_name: string, layer_type: array{array{availability?: int, displayName: string, value: int}}, layer_info_properties: ?array{array{property_name: string, policy_type?: string}}}}, expertise_definitions: array, oceanview: array, objectives: array, region: string, projection: string, edition_name: string, edition_colour: string, edition_letter: string, start: int, end: int, era_total_months: int, era_planning_months: int, era_planning_realtime: int, countries: string, minzoom: int, maxzoom: int, user_admin_name: string, user_region_manager_name: string, user_admin_color: string, user_region_manager_color: string, team_info_base_url: string, region_base_url: string, restriction_point_size: int, wiki_base_url: string, windfarm_data_api_url: ?string}|array{application_versions: array{client_build_date_min: string, client_build_date_max: string}, restriction_point_size?: float, wiki_base_url: string}
     * @throws Exception
     */
    private function getDataModel(int $sessionId): array
    {
        if (null === $gameList = $this->em->getRepository(GameList::class)->find($sessionId)) {
            throw new Exception('Game list not found');
        }
        if (null === $config = $gameList->getGameConfigVersion()->getGameConfigComplete()) {
            throw new Exception('Game config not found');
        }
        if (null === $dataModel = ($config['datamodel'] ?? null)) {
            throw new Exception('Data model not found');
        }
        return $dataModel;
    }
}
