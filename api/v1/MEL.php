<?php

namespace App\Domain\API\v1;

use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\EntityEnums\PlanLayerState;
use App\Domain\Common\InternalSimulationName;
use App\Domain\PolicyData\BufferZonePolicyData;
use App\Domain\PolicyData\ItemsPolicyDataBase;
use App\Domain\PolicyData\PolicyDataBase;
use App\Domain\PolicyData\PolicyDataFactory;
use App\Domain\PolicyData\ScheduleFilterPolicyData;
use App\Domain\PolicyData\SeasonalClosurePolicyData;
use App\Domain\Services\ConnectionManager;
use App\Entity\SessionAPI\Geometry;
use App\Entity\SessionAPI\Simulation;
use App\Repository\SessionAPI\SimulationRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NonUniqueResultException;
use Exception;

class MEL extends Base
{
    private ?\PDO $pdo = null;

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Config(): ?array
    {
        $gameConfigValues = (new Game())->GetGameConfigValues();
        $melConfig = $gameConfigValues['MEL'] ?? null;

        // a mel pressure could hold a policy_filters field
        $pressures = $melConfig['pressures'];
        foreach ($pressures as $pressureIndex => $pressure) {
            if (empty($pressure['policy_filters'])) {
                continue;
            }
            // if so, traverse all pressure layers and try to find the corresponding layer in the meta section
            foreach ($pressure['layers'] as $pressureLayerIndex => $pressureLayer) {
                if (empty($pressureLayer['name'])) {
                    continue;
                }
                if (null === $layer = collect($gameConfigValues['meta'] ?? [])->filter(
                    fn($l) => $l['layer_name'] == $pressureLayer['name']
                )->first()) {
                    continue;
                }
                // if found, check if the meta layer has a layer info property for policies
                $layerInfoPolicyTypeProps = collect($layer['layer_info_properties'] ?? [])->filter(
                    fn($p) => !empty($p['policy_type'])
                )->all();
                if (empty($layerInfoPolicyTypeProps)) {
                    continue;
                }
                // if so, apply those pressure policy filters to the pressure layer
                $melConfig['pressures'][$pressureIndex]['layers'][$pressureLayerIndex]['policy_filters'] =
                    $pressure['policy_filters'];
            }
        }

        return $melConfig;
    }

    /**
     * @throws Exception
     */
    public function getFishingNameByFleetIndex(int $fleetIndex): ?string
    {
        $game = new Game();
        $this->asyncDataTransferTo($game);
        $gameConfigValues = $game->GetGameConfigValues();
        if (null === ($melConfig = $gameConfigValues['MEL'] ?? null)) {
            return null;
        }
        $fishing = collect($melConfig['fishing'])
            ->filter(fn($f) => in_array($fleetIndex, $f['policy_filters']['fleets'] ?? []))
            ->first();
        return $fishing['name'] ?? null;
    }

    /**
     * @return int[]
     * @throws Exception
     */
    public function getFleetIndicesFromFishingName(string $fishingName): array
    {
        $game = new Game();
        $this->asyncDataTransferTo($game);
        $gameConfigValues = $game->GetGameConfigValues();
        if (null === ($melConfig = $gameConfigValues['MEL'] ?? null)) {
            return [];
        }
        $fishing = collect($melConfig['fishing'])
            ->filter(fn($f) => $f['name'] === $fishingName)
            ->first();
        return array_map(fn($i) => intval($i), $fishing['policy_filters']['fleets'] ?? []);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function OnReimport(array $config): void
    {
        //wipe the table for testing purposes
        $db = $this->getDatabase();
        $db->query("TRUNCATE TABLE mel_layer");
        $db->query("TRUNCATE TABLE fishing");

        //Check the config file.
        if (isset($config["fishing"])) {
            $countries = $db->query("SELECT * FROM country WHERE country_is_manager = 0");
            foreach ($config["fishing"] as $fleet) {
                if (isset($fleet["initial_fishing_distribution"])) {
                    foreach ($countries as $country) {
                        $foundCountry = false;
                        foreach ($fleet["initial_fishing_distribution"] as $distribution) {
                            if ($distribution["country_id"] == $country["country_id"]) {
                                $foundCountry = true;
                                break;
                            }
                        }

                        if (!$foundCountry) {
                            throw new Exception(
                                "Country with ID ".$country["country_id"].
                                " is missing a distribution entry in the initial_fishing_distribution table for fleet ".
                                $fleet["name"]." for MEL."
                            );
                        }
                    }
                }
            }
        }

        foreach ($config['pressures'] as $pressure) {
            $pressureId = $this->SetupMELLayer($pressure['name'], $config);

            if ($pressureId != -1) {
                foreach ($pressure['layers'] as $layer) {
                    $layerid = $db->query(
                        "SELECT layer_id FROM layer WHERE layer_name=?",
                        array($layer['name'])
                    );
                    if (!empty($layerid)) {
                        $layerid = $layerid[0]['layer_id'];

                        $mellayer = $db->query(
                            "
                            SELECT mel_layer_id FROM mel_layer WHERE mel_layer_pressurelayer=? AND mel_layer_layer_id=?
                            ",
                            array($pressureId, $layerid)
                        );
                        if (empty($mellayer)) {
                            //add a layer to the mel_layer table for faster accessing
                            $db->query(
                                "INSERT INTO mel_layer (mel_layer_pressurelayer, mel_layer_layer_id) VALUES (?, ?)",
                                array($pressureId, $layerid)
                            );
                        }
                    }
                }
            }
        }

        foreach ($config['outcomes'] as $outcome) {
            $this->SetupMELLayer($outcome['name'], $config);
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function SetupMELLayer(string $melLayerName, array $config): int
    {
        $layerName = "mel_" . str_replace(" ", "_", $melLayerName);
        $data = $this->getDatabase()->query(
            "SELECT layer_id, layer_raster FROM layer WHERE layer_name=?",
            array($layerName)
        );

        $game = new Game();
        $globalConfig = $game->GetGameConfigValues();
        $layerMeta = current(array_filter($globalConfig['meta'], function ($meta) use ($layerName) {
            return strcasecmp($meta['layer_name'], $layerName) === 0;
        }));
        // take the config's layer name since the case of the characters can be different from MEL's layer name.
        $layerName = $layerMeta['layer_name'] ?? $layerName;
        $rasterProperties = array(
            "url" => "$layerName.tif",
            "boundingbox" => array(
                array($config["x_min"], $config["y_min"]), array($config["x_max"], $config["y_max"])
            )
        );

        if (empty($data)) {
            //create new layer
            Log::LogDebug("Note: found reference to MEL layer {$layerName}. Please check its existence under 'meta'.");
            $rasterFormat = json_encode($rasterProperties);
            $layerId = $this->getDatabase()->query(
                "
                INSERT INTO layer (
                    layer_name, layer_short, layer_geotype, layer_group, layer_category, layer_subcategory, layer_raster
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ",
                [$layerName, $melLayerName, "raster", $globalConfig['region'], "Ecology", "pressure", $rasterFormat],
                true
            );
        } else {
            $layerId = $data[0]['layer_id'];
            $existingRasterProperties = json_decode($data[0]['layer_raster'], true);
            $rasterProperties = array_merge($existingRasterProperties ?? array(), $rasterProperties);
            $rasterFormat = json_encode($rasterProperties);
            $this->getDatabase()->query(
                "UPDATE layer SET layer_raster=? WHERE layer_id = ?",
                array($rasterFormat, $layerId)
            );
        }
        return $layerId;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function InitialFishing(array $fishing_values): array
    {
        $existingPlans = $this->getDatabase()->query(
            "SELECT plan.plan_id FROM plan WHERE plan.plan_gametime = -1 AND (plan.plan_type & ? = ?)",
            [GeneralPolicyType::FISHING, GeneralPolicyType::FISHING]
        );
        if (count($existingPlans) > 0) {
            // In this case we already have something in the database that is a fishing plan, might be of a previous
            //   instance of MEL on this session or a starting plan.
            //   Don't insert any new values in the database to avoid the fishing values increasing every start of MEL.
            $this->log('Found existing fishing plan, canceling fishing values insertion');
            return [
                'log' => $this->getLogMessages()
            ];
        }

        // insert starting plan.
        $planId = $this->getDatabase()->query(
            "
            INSERT INTO plan (
                plan_name, plan_country_id, plan_gametime, plan_state, plan_type) VALUES (?, ?, ?, ?, ?
            )
            ",
            array("", 1, -1, "IMPLEMENTED", GeneralPolicyType::FISHING),
            true
        );

        $game = new Game();
        $this->asyncDataTransferTo($game);
        $gameConfigValues = $game->GetGameConfigValues();
        // no fleets defined, return
        if (empty($gameConfigValues['policy_settings']['fishing']['fleet_info']['fleets'])) {
            $this->error('No policy_settings->fishing->fleet_info->fleets defined in the config.');
            return [
                'log' => $this->getLogMessages()
            ];
        }

        $fishingFleetNameToValue = collect($fishing_values)->keyBy('fleet_name')
            ->map(fn($f) => $f['fishing_value'])
            ->all();
        foreach ($gameConfigValues['policy_settings']['fishing']['fleet_info']['fleets'] as $fleetIndex => $fleet) {
            $fleetDescription = $fleet['//comment'] ?? 'index '.$fleetIndex;

            // fleet it not defined in the MEL->fishing config, so skip it
            if (null == $fishingFleetName = $this->getFishingNameByFleetIndex($fleetIndex)) {
                $this->log(
                    'Could not match MEL->fishing with fleet '. $fleetDescription.
                    ', are you missing field policy_filters->fleets, or are you missing a name field?',
                    self::LOG_LEVEL_WARNING
                );
                continue;
            }
            if (!array_key_exists($fishingFleetName, $fishingFleetNameToValue)) {
                $this->log(
                    'Could not find fishing value for fleet '.$fleetDescription. ', with name: '.$fishingFleetName,
                    self::LOG_LEVEL_WARNING
                );
                continue;
            }
            if (empty(trim($fleet['country_id']))) {
                $this->log('Country ID is not set, or 0, for fleet '.$fleetDescription, self::LOG_LEVEL_WARNING);
                continue;
            }
            // a specific country is set for this fleet, so a national fleet
            if ($fleet['country_id'] != -1) {
                $this->getDatabase()->query(
                    "
                    INSERT INTO fishing (
                        fishing_country_id, fishing_plan_id, fishing_type, fishing_amount, fishing_active
                    ) VALUES (?, ?, ?, ?, ?)
                    ",
                    array(
                        $fleet['country_id'], $planId, $fishingFleetName, $fishingFleetNameToValue[$fishingFleetName], 1
                    )
                );
                $this->log(sprintf(
                    'Inserted fishing value %.2f, for national fleet %s, with name %s, for country %d',
                    $fishingFleetNameToValue[$fishingFleetName],
                    $fleetDescription,
                    $fishingFleetName,
                    $fleet['country_id']
                ));
                continue;
            }

            // a global fleet, so insert for all countries, but weighted using initial_fishing_distribution
            //  if initial_fishing_distribution is not set, show a warning, but fall-back to equal distribution\
            $countries = $this->getDatabase()->query('SELECT country_id FROM country WHERE country_is_manager != 1');
            if (!isset($fleet["initial_fishing_distribution"])) {
                $this->log(
                    'No initial_fishing_distribution set for fleet '.$fleetDescription.', no distributions used.',
                    self::LOG_LEVEL_WARNING
                );
                foreach ($countries as $country) {
                    $this->getDatabase()->query(
                        "
                        INSERT INTO fishing (
                            fishing_country_id, fishing_plan_id, fishing_type, fishing_amount, fishing_active
                        ) VALUES (?, ?, ?, ?, ?)
                        ",
                        [
                            $country['country_id'], $planId, $fishingFleetName,
                            $fishingFleetNameToValue[$fishingFleetName],
                            1
                        ]
                    );
                    $this->log(sprintf(
                        'Inserted fishing value  %.2f, for global fleet %s, with name'.
                        ' %s, for country %d without distributions',
                        $fishingFleetNameToValue[$fishingFleetName],
                        $fleetDescription,
                        $fishingFleetName,
                        $country['country_id']
                    ));
                }
                continue;
            }

            // log error if one of the initial_fishing_distribution entries is missing a country_id or effort_weight
            $numMissingCountryIdFields = collect($fleet["initial_fishing_distribution"])->filter(
                fn($val) => !isset($val["country_id"])
            )->count();
            if ($numMissingCountryIdFields > 0) {
                $this->log(sprintf(
                    'initial_fishing_distribution for fleet %s is missing %d country_id fields',
                    $fleetDescription,
                    $numMissingCountryIdFields
                ), self::LOG_LEVEL_ERROR);
            }
            $numMissingEffortWeightFields = collect($fleet["initial_fishing_distribution"])->filter(
                fn($val) => !isset($val["effort_weight"])
            )->count();
            if ($numMissingEffortWeightFields > 0) {
                $this->log(sprintf(
                    'initial_fishing_distribution for fleet %s is missing %d effort_weight fields',
                    $fleetDescription,
                    $numMissingEffortWeightFields
                ), self::LOG_LEVEL_ERROR);
            }

            $numCountries = count($countries);
            $weightsByCountry = [];
            //We need to average the weights over the available countries
            $sum = 0.0;
            foreach ($fleet["initial_fishing_distribution"] as $val) {
                if (!isset($val["effort_weight"]) || !isset($val["country_id"])) {
                    continue;
                }
                $sum += $val["effort_weight"];
                $weightsByCountry[$val["country_id"]] = $val["effort_weight"];
            }
            $weightMultiplier = ($sum > 0) ? 1.0 / $sum : 1.0 / $numCountries;
            foreach ($weightsByCountry as &$countryWeight) {
                $countryWeight *= $weightMultiplier;
            }
            foreach ($countries as $country) {
                $v = $fishingFleetNameToValue[$fishingFleetName] * ($weightsByCountry[$country['country_id']] ?? 1);
                $this->getDatabase()->query(
                    "
                    INSERT INTO fishing (
                        fishing_country_id, fishing_plan_id, fishing_type, fishing_amount, fishing_active
                    ) VALUES (?, ?, ?, ?, ?)
                    ",
                    array(
                        $country['country_id'],
                        $planId,
                        $fishingFleetName,
                        $v,
                        1
                    )
                );
                $this->log(sprintf(
                    'Inserted fishing value %.2f, for global fleet %s, with name %s, for country %d'.
                    ' with distribution %.2f',
                    $v,
                    $fleetDescription,
                    $fishingFleetName,
                    $country['country_id'],
                    ($weightsByCountry[$country['country_id']] ?? 1)
                ));
            }
        }
        return [
            'log' => $this->getLogMessages()
        ];
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateLayer(string $layer_name): void
    {
        $this->getDatabase()->query(
            "UPDATE layer SET layer_lastupdate=UNIX_TIMESTAMP(NOW(6)) WHERE layer_name=?",
            array($layer_name)
        );
    }

    /**
     * @return int[]
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetEcoGearFleets(): array
    {
        $connection = ConnectionManager::getInstance()->getCachedGameSessionDbConnection($this->getGameSessionId());
        $result = $connection->executeQuery(
            <<<'SQL'
            WITH EcoGearPolicies AS (
                SELECT
                    p.plan_id,
                    p.plan_lastupdate,
                    JSON_UNQUOTE(JSON_EXTRACT(item, '$.enabled')) AS enabled,
                    JSON_UNQUOTE(JSON_EXTRACT(item, '$.fleets')) AS fleets
                FROM
                    plan p
                INNER JOIN
                    plan_policy pp ON p.plan_id = pp.plan_id AND
                        (p.plan_state = 'APPROVED' OR p.plan_state = 'IMPLEMENTED') AND
                        p.plan_gametime <= :currentMonth
                INNER JOIN
                    policy po ON pp.policy_id = po.id
                CROSS JOIN
                    JSON_TABLE(po.data, '$.items[*]' COLUMNS (
                        item JSON PATH '$'
                    )) jt
                WHERE
                    po.type = 'eco_gear'
            ),
            EnabledFleets AS (
                SELECT
                    e.plan_id,
                    e.plan_lastupdate,
                    JSON_UNQUOTE(JSON_EXTRACT(fleet.value, '$')) AS fleet_id,
                    e.enabled
                FROM
                    EcoGearPolicies e
                CROSS JOIN
                    JSON_TABLE(e.fleets, '$[*]' COLUMNS (
                        value JSON PATH '$'
                    )) fleet
            ),
            LatestFleetStatus AS (
                SELECT
                    fleet_id,
                    plan_id,
                    enabled,
                    ROW_NUMBER() OVER (PARTITION BY fleet_id ORDER BY plan_lastupdate DESC) AS rn
                FROM
                    EnabledFleets
            )
            SELECT
                fleet_id
            FROM
                LatestFleetStatus
            WHERE
                rn = 1
            AND
                enabled = 'true';            
            SQL,
            ['currentMonth' => (new Game())->GetCurrentMonthAsId()]
        );
        return array_map('intval', $result->fetchFirstColumn());
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Update(): array
    {
//SET @month = 4;
//SET @prevMonth = 2;
//
//SELECT DISTINCT layer_name, layer_melupdate
//FROM layer
//WHERE layer_melupdate = 1
//OR layer_id IN (
//  WITH geometry_with_months AS (
//      SELECT g.geometry_layer_id, jt.months
//      FROM geometry g
//      JOIN JSON_TABLE(JSON_UNQUOTE(JSON_EXTRACT(g.geometry_data, '$.SEASONAL_CLOSURE')), '$.items[*]'
//          COLUMNS (
//              months JSON PATH '$.months'
//          )
//      ) jt ON TRUE
//  )
//  SELECT DISTINCT l.layer_original_id
//  FROM layer l
//  JOIN geometry_with_months g ON g.geometry_layer_id=l.layer_id AND
//    (
//        g.months & @prevMonth != @prevMonth
//        AND g.months & @month = @month
//    ) OR (
//        g.months & @prevMonth = @prevMonth
//        AND g.months & @month != @month
//    )
//);

        $game = new Game();
        $policyLayerProps = collect($game->getPolicyLayerPropertiesFromConfig())->keyBy('property_name')->all();
        $currentMonth = $game->GetCurrentMonthAsId();
        $conn = ConnectionManager::getInstance()->getCachedGameSessionDbConnection($this->getGameSessionId());
        $qb = $conn->createQueryBuilder();
        $qb
            ->select('layer_name')
            ->from('layer')
            // layers that have a melupdate flag set
            ->where($qb->expr()->eq('layer_melupdate', 1));
        foreach ($policyLayerProps as $layerProp) {
            // or, any layer with a schedule policy filter matching one of these criteria:
            // * the previous month was not listed but the current month is
            // * the current month is listed, but the previous month is not
            $qb->orWhere($qb->expr()->in(
                'layer_id',
                sprintf(
                    <<< 'SUBQUERY'
                    WITH
                        geometry_with_months AS (
                            SELECT g.geometry_layer_id, jt.months
                            FROM
                                geometry g 
                                JOIN JSON_TABLE(JSON_UNQUOTE(JSON_EXTRACT(g.geometry_data, '$.%s')), '$.items[*]'
                                    COLUMNS (
                                        months JSON PATH '$.months'
                                    )
                                ) jt ON TRUE
                        )
                    SELECT DISTINCT l.layer_original_id
                    FROM layer l
                    JOIN geometry_with_months g ON g.geometry_layer_id=l.layer_id
                    AND (
                        (
                            g.months & :prevMonth != :prevMonth
                            AND g.months & :month = :month
                        ) OR (
                            g.months & :prevMonth = :prevMonth
                            AND g.months & :month != :month
                        )
                   )
SUBQUERY,
                    $layerProp['property_name']
                )
            ))
            ->setParameter('month', pow(2, $currentMonth % 12))
            ->setParameter('prevMonth', pow(2, (($currentMonth-1) % 12)));
        }

        $r = $qb->executeQuery()->fetchAllAssociative();

        /** @noinspection SqlWithoutWhere */
        $this->getDatabase()->query("UPDATE layer SET layer_melupdate=0");
        $a = array_column($r, 'layer_name');
        $a['debug-message'] = 'Current month ID: '.$currentMonth;
        return $a;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Latest(float $time): void
    {
    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ShouldUpdate(int $mel_month): int
    {
        $game = new Game();
        $currentMonth = $game->GetCurrentMonthAsId();

        if ($mel_month < $currentMonth) {
            return $currentMonth; //was echoed
        } else {
            return -100; //was echoed
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function TickDone(): void
    {
        /** @var SimulationRepository $repo */
        $repo = ConnectionManager::getInstance()->getGameSessionEntityManager($this->getGameSessionId())
            ->getRepository(Simulation::class);
        $game = new Game();
        $repo->notifyMonthFinishedForInternal(InternalSimulationName::MEL, $game->GetCurrentMonthAsId());
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetFishing(int $game_month): array
    {
        $data = $this->getDatabase()->query(
            "SELECT SUM(fishing_amount) as scalar, fishing_type as name FROM fishing 
									LEFT JOIN plan ON plan.plan_id=fishing.fishing_plan_id
									WHERE fishing_active = 1 AND plan_gametime <= ?
									GROUP BY fishing_type",
            array($game_month)
        );

        //Make sure fishing scalars never exceed 1.0
        foreach ($data as &$fishingValues) {
            if (floatval($fishingValues['scalar']) > 1.0) {
                $fishingValues['scalar'] = 1.0;
            }
        }

        return $data;
    }

    /**
     * @param int $layer_type the layer type is used as a filter for the layer's geometry field "geometry_type"
     *   which holds an optional comma separated string holding integers.
     * E.g. For MPAs those refer to a fleet 1,2 or 3.
     *   So if the filter is set to 1,2 or 3, it will only return geometries referring that fleet in the string
     *   1 = No Bottom Trawl Fleets
     *   2 = No Industrial and Pelagic Trawl Fleets
     *   3 = No Drift and Fixed Nets Fleets
     *
     * @apiGroup MEL
     * @apiDescription Export/get all the geometry of a layer by:
     * - layer name
     * - optionally, the layer type, which is used to filter on the geometry type
     * - optionally, flag to only get ones being constructed
     * - optionally, policy filters to apply
     * @throws Exception
     * @api {POST} /mel/GeometryExportName Geometry Export Name
     * @apiParam {string} layer name to return the geometry data for
     * @apiParam {int} layer_type type within the layer to return. -1 for all types.
     * @apiParam {bool} construction_only whether to return data only if it's being constructed.
     * @apiParam {string} policy_filters JSON object with the policy filters to apply
     * @apiSuccess {string} JSON Object
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GeometryExportName(
        string $name,
        int $layer_type = -1,
        bool $construction_only = false,
        ?string $policy_filters = null
    ): ?array {
        if ($policy_filters != null) {
            $policy_filters = (json_decode($policy_filters) ?? false) ?: null;
        }
        return $this->exportAllGeometryFromLayer(
            $name,
            $layer_type,
            $construction_only,
            $policy_filters
        );
    }

    /**
     * @param string $name
     * @param int $geometryType
     * @param bool $constructionOnly
     * @param object|null $policyFilters
     *
     * @return array|null
     * @throws NonUniqueResultException
     */
    private function exportAllGeometryFromLayer(
        string $name,
        int $geometryType = -1,
        bool $constructionOnly = false,
        ?object $policyFilters = null
    ): ?array {
        $conn = ConnectionManager::getInstance()->getGameSessionEntityManager($this->getGameSessionId());
        $qb = $conn->createQueryBuilder();
        /** @var ?\App\Entity\SessionAPI\Layer $layer */
        $layer = $qb
            ->from('App:Layer', 'l')
            ->select('l')
            ->where('l.layerName = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT);
        if (null === $layer) {
            return null;
        }

        $result = [];
        $layerGeoType = $layer->getLayerGeoType();
        if ($layerGeoType == LayerGeoType::RASTER) {
            $result["geotype"] = $layerGeoType?->value ?? ''; // enum to string
            $result["raster"] = $layer->getLayerRaster()['url'] ?? '';
            return $result;
        }

        $layerId = $layer->getLayerId() ?? 0;
        $qb = $conn->createQueryBuilder();
        $qb
            ->select('l, pl, ge') // p, pp, pol, pt, ptft, pft
            ->from('App:Layer', 'l')
            ->leftJoin('l.planLayer', 'pl')
            ->leftJoin('l.geometry', 'ge')
            ->where('l.layerId = :layerId OR l.originalLayer = :layerId')
            ->setParameter('layerId', $layerId)
            ->andWhere('ge.geometryActive = 1');
        if ($constructionOnly) {
            $qb
                ->andWhere('pl.planLayerState = :planLayerState')
                ->setParameter('planLayerState', PlanLayerState::ASSEMBLY->value);
        } else {
            $qb
                ->andWhere(
                    $qb->expr()->orX(
                        'pl.planLayerState = :planLayerState',
                        'pl.planLayerState IS NULL'
                    )
                )
                ->setParameter('planLayerState', PlanLayerState::ACTIVE->value);
        }
        if ($geometryType != -1) {
            $qb
                ->andWhere(
                    $qb->expr()->like('ge.geometryType', ':geometryType')
                )
                ->setParameter('geometryType', "%$geometryType%");
            $this->log('Filtering on geometry type: '.$geometryType);
        }

        $q = $qb->getQuery();
        /** @var \App\Entity\SessionAPI\Layer[] $layers */
        $layers = $q->getResult();
        $result["geotype"] = $layerGeoType ?->value ?? ''; // enum to string;
        $result["geometry"] = [];
        foreach ($layers as $l) {
            // export each geometry linked to the layer
            foreach ($l->getGeometry() as $geom) {
                $this->exportGeometryTo($geom, $policyFilters, $result["geometry"]);
            }
        }
        $result['logs'] = json_encode($this->getLogMessages());
        return $result;
    }

    public function applyGeometryBufferZonePolicy(
        Geometry $geom,
        BufferZonePolicyData $policyData,
        array $options
    ): ?array {
        $this->log('applied policy '.$policyData->getPolicyTypeName()->value.' with : '.$policyData->radius.'.');

        // todo(MH) there is probably a way to get it working with
        //   ConnectionManager::getInstance()->getCachedServerManagerDbConnection()->getNativeConnection()
        $this->pdo ??= new \PDO("mysql:host=$_ENV[DATABASE_HOST];port=$_ENV[DATABASE_PORT]", 'root', '');
        // convert our geometry using mariadb's GIS features
        try {
            if ($options['include_original_polygon'] ?? false) {
                // buffer including the original polygon
                $st = $this->pdo->prepare(
                    <<< 'SQL'
                    SELECT AsText(BUFFER(GeomFromText(:text, 3035), :buffer))
                    SQL
                );
            } else {
                // buffer excluding the original polygon
                $st = $this->pdo->prepare(
                    <<< 'SQL'
                    SELECT AsText(ST_SYMDIFFERENCE(
                      BUFFER(GeomFromText(:text, 3035), :buffer),
                      GeomFromText(:text, 3035)
                    ))
                    SQL
                );
            }
            $st->bindValue('text', self::toWkt(json_decode($geom->getGeometryGeometry(), true)));
            $st->bindValue('buffer', $policyData->radius);
            $st->execute();
            // for debugging use https://wktmap.com/ to visualize using EPSG:3035 !
            $bufferedPolygonText = $st->fetchColumn();
            return self::fromWkt($bufferedPolygonText);
        } catch (\Exception $e) {
            while (null !== $prev = $e->getPrevious()) {
                $e = $prev;
            }
            $this->log($e->getMessage() . PHP_EOL, self::LOG_LEVEL_ERROR);
            return null;
        }
    }

    private function createObjectFromFiltersPolicyData(?object ...$filters): \stdClass
    {
        $object = new \stdClass();
        foreach ($filters as $filter) {
            if (null === $filter) {
                continue;
            }
            $props = get_object_vars($filter);
            foreach ($props as $prop => $value) {
                $object->$prop = $value;
            }
        }
        return $object;
    }

    /**
     * @param Geometry $geometry
     * @param object|null $policyFilters
     * @param array $exportResult
     * @throws Exception
     */
    private function exportGeometryTo(
        Geometry $geometry,
        ?object $policyFilters,
        array &$exportResult,
    ): void {
        $layer = $geometry->getLayer()->getOriginalLayer() ?? $geometry->getLayer();
        $policyLayerProps = collect(
            (new Game())->getPolicyLayerPropertiesFromConfig($layer->getLayerName())
        )->keyBy('property_name')->all();
        if (empty($policyLayerProps)) { // no polices found, default handling then
            $this->addGeometryToResult($geometry, $exportResult);
            return;
        }
        // filter all policy geometry data properties and convert them to json objects
        $geomDataProperties = array_map(
            fn($s) => json_decode($s),
            array_filter(
                $geometry->getGeometryDataAsJsonDecoded(true) ?? [],
                fn($k) => array_key_exists($k, $policyLayerProps),
                ARRAY_FILTER_USE_KEY
            )
        );

        /** @var PolicyDataBase[]|ItemsPolicyDataBase[] $policiesToApply */
        $policiesToApply = [];
        foreach ($geomDataProperties as $policyData) {
            $g = $geometry->getOriginalGeometry() ?? $geometry;
            $this->log('Encountered policies for geometry: '.($geometry->getName() ?? $g->getName() ?? 'unnamed'));
            if (!is_object($policyData)) {
                $this->log('Policy data is not a json object: '.json_encode($policyData), self::LOG_LEVEL_WARNING);
                continue;
            }
            // convert json object to an actual PolicyData instance
            try {
                $policyData = PolicyDataFactory::createPolicyDataByJsonObject($policyData);
            } catch (\Exception $e) {
                $this->log($e->getMessage(), self::LOG_LEVEL_ERROR);
                $this->log(
                    'Could not create policy data from json object: '.json_encode($policyData),
                    self::LOG_LEVEL_ERROR
                );
                continue;
            }
            $filterResult = $policyData->matchFiltersOn($this->createObjectFromFiltersPolicyData(
                $policyFilters,
                ScheduleFilterPolicyData::createFromGameMonth((new Game())->GetCurrentMonthAsId())
            ));
            $this->appendFromLogContainer($policyData);
            if (false === $filterResult) {
                $this->log('Skipping policy');
                continue;
            }
            $policiesToApply[] = $policyData;
        }
        if (empty($policiesToApply)) { // polices were filtered out, do nothing
            return;
        }
        $this->log(
            'Applying policies of type: '.
                implode(', ', array_map(fn($p) => $p->getPolicyTypeName()->value, $policiesToApply))
        );
        /** @var BufferZonePolicyData[] $bufferZonePolicyDataContainer */
        $bufferZonePolicyDataContainer = array_filter($policiesToApply, fn($p) => $p instanceof BufferZonePolicyData);
        if (!empty($bufferZonePolicyDataContainer)) {
            $includeOriginalPolygon =
                !empty(array_filter($policiesToApply, fn($p) => $p instanceof SeasonalClosurePolicyData));
            // apply buffer zone policy, and seasonal closure policy if it exists
            $exportResult[] = $this->applyGeometryBufferZonePolicy(
                $geometry,
                current($bufferZonePolicyDataContainer),
                [ 'include_original_polygon' => $includeOriginalPolygon ],
            );
            return; // all types are handled
        }
        // just a seasonal closure policy
        $this->addGeometryToResult($geometry, $exportResult);
    }

    private function addGeometryToResult(
        Geometry $geom,
        array    &$result
    ): void {
        $geometryPoints = $geom->getGeometryGeometry();
        if (empty($geometryPoints)) {
            return;
        }
        // add extra array layer.
        // needed for polygons: 1 entry is exterior ring, 2nd and onwards are interior rings
        //   only an exterior ring in this case.
        $result[] = [json_decode($geometryPoints, true)];
    }

    private static function toWkt(array $coordinates): string
    {
        // if the first and last element are not the same, add the first element to the end
        if ($coordinates[0] !== end($coordinates)) {
            $coordinates[] = $coordinates[0];
        }
        $originalPolygonCoordsText = implode(
            ',',
            array_map(fn($p) => implode(' ', $p), $coordinates)
        );
        return 'POLYGON(('.$originalPolygonCoordsText.'))';
    }

    private static function fromWkt(string $wkt): array
    {
        // Strip "POLYGON(" and the trailing ")"
        $wkt = substr($wkt, strlen("POLYGON("), -1);
        // Match inner parts between parentheses
        preg_match_all('/\((.*?)\)/', $wkt, $matches);
        $rings = $matches[1];
        $coordinates = array();
        // Iterate through each ring
        foreach ($rings as $ring) {
            $points = explode(',', $ring);
            $rCoordinates = array();
            // Iterate through each point
            foreach ($points as $point) {
                list($x, $y) = explode(' ', trim($point));
                $rCoordinates[] = array((float)$x, (float)$y);
            }
            $coordinates[] = $rCoordinates;
        }
        return $coordinates;
    }
}
