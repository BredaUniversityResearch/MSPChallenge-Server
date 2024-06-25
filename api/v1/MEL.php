<?php

namespace App\Domain\API\v1;

use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\EntityEnums\PlanLayerState;
use App\Domain\Common\EntityEnums\PolicyFilterTypeName;
use App\Domain\PolicyData\BufferZonePolicyData;
use App\Domain\PolicyData\PolicyDataBase;
use App\Domain\PolicyData\ItemsPolicyDataBase;
use App\Domain\PolicyData\PolicyDataFactory;
use App\Domain\PolicyData\ScheduleFilterPolicyData;
use App\Domain\Services\ConnectionManager;
use App\Entity\Geometry;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NonUniqueResultException;
use Exception;

class MEL extends Base
{
    private ?\PDO $pdo = null;

    private const ALLOWED = array(
        "OnReimport",
        "Config",
        "UpdateLayer",
        "ShouldUpdate",
        "Update",
        "TickDone",
        "GetFishing",
        "GeometryExportName",
        "GetEcoGearFleets",
        "InitialFishing"
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Config(): ?array
    {
        $gameConfigValues = (new Game())->GetGameConfigValues();
        return $gameConfigValues['MEL'] ?? null;
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
                if (isset($fleet["initialFishingDistribution"])) {
                    foreach ($countries as $country) {
                        $foundCountry = false;
                        foreach ($fleet["initialFishingDistribution"] as $distribution) {
                            if ($distribution["country_id"] == $country["country_id"]) {
                                $foundCountry = true;
                                break;
                            }
                        }

                        if (!$foundCountry) {
                            throw new Exception(
                                "Country with ID ".$country["country_id"].
                                " is missing a distribution entry in the initialFishingDistribution table for fleet ".
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
    public function InitialFishing(array $fishing_values): void
    {
        $existingPlans = $this->getDatabase()->query(
            "SELECT plan.plan_id FROM plan WHERE plan.plan_gametime = -1 AND (plan.plan_type & ? = ?)",
            [GeneralPolicyType::FISHING, GeneralPolicyType::FISHING]
        );
        if (count($existingPlans) > 0) {
            // In this case we already have something in the database that is a fishing plan, might be of a previous
            //   instance of MEL on this session or a starting plan.
            //   Don't insert any new values in the database to avoid the fishing values increasing every start of MEL.
            return;
        }

        $countries = $this->getDatabase()->query("SELECT country_id FROM country WHERE country_is_manager != 1");
        $numCountries = count($countries);

        $planid = $this->getDatabase()->query(
            "
            INSERT INTO plan (
                plan_name, plan_country_id, plan_gametime, plan_state, plan_type) VALUES (?, ?, ?, ?, ?
            )
            ",
            array("", 1, -1, "IMPLEMENTED", GeneralPolicyType::FISHING),
            true
        );

        $config = $this->Config();
        $weightsByFleet = array();
        if (isset($config["fishing"])) {
            $fishingFleets = $config["fishing"];
            foreach ($fishingFleets as $fishingFleet) {
                $weightsByCountry = array();

                if (isset($fishingFleet["initialFishingDistribution"])) {
                    $fishingValues = $fishingFleet["initialFishingDistribution"];

                    //We need to average the weights over the available countries
                    $sum = 0.0;
                    foreach ($fishingValues as $val) {
                        if (isset($val["weight"]) && isset($val["country_id"])) {
                            $sum += $val["weight"];
                            $weightsByCountry[$val["country_id"]] = $val["weight"];
                        }
                    }

                    $weightMultiplier = ($sum > 0)? 1.0 / $sum : 1.0 / $numCountries;
                    foreach ($weightsByCountry as &$countryWeight) {
                        $countryWeight *= $weightMultiplier;
                    }

                    $weightsByFleet[$fishingFleet["name"]] = $weightsByCountry;
                }
            }
        }

        foreach ($fishing_values as $fishing) {
            $name = $fishing["fleet_name"];

            foreach ($countries as $country) {
                $countryId = $country["country_id"];
                $weight = $weightsByFleet[$name][$countryId] ?? 1;
                $this->getDatabase()->query(
                    "
                    INSERT INTO fishing (
                        fishing_country_id, fishing_plan_id, fishing_type, fishing_amount, fishing_active
                    ) VALUES (?, ?, ?, ?, ?)
                    ",
                    array($country['country_id'], $planid, $name, $fishing["fishing_value"] * $weight, 1)
                );
            }
        }
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
                    plan_policy pp ON p.plan_id = pp.plan_id
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
            SQL
        );
        return array_map('intval', $result->fetchFirstColumn());
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Update(): array
    {
        $currentMonth = (new Game())->GetCurrentMonthAsId();
        $conn = ConnectionManager::getInstance()->getCachedGameSessionDbConnection($this->getGameSessionId());
        $qb = $conn->createQueryBuilder();
        $qb
            ->select('l.layer_name')
            ->from('layer', 'l')
            // layers that have a melupdate flag set
            ->where($qb->expr()->eq('l.layer_melupdate', 1))
            // or, any layer with a schedule policy filter matching one of these criteria:
            // * the previous month was not listed but the current month is
            // * the current month is listed, but the previous month is not
            ->orWhere($qb->expr()->in(
                'l.layer_id',
                <<< 'SUBQUERY'
                WITH
                    geometry_with_months AS (
                        SELECT g.geometry_layer_id, jt.months
                        FROM
                            geometry g 
                            JOIN JSON_TABLE(g.geometry_data, '$.policies[*]'
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
                        JSON_CONTAINS(g.months, :prevMonth, '$')=1
                        AND JSON_CONTAINS(g.months, :month, '$')=0
                    ) OR (
                        JSON_CONTAINS(g.months, :prevMonth, '$')=0
                        AND JSON_CONTAINS(g.months, :month, '$')=1
                    )
               )
SUBQUERY
            ))
            ->setParameter('pftName', PolicyFilterTypeName::SCHEDULE->value)
            ->setParameter('month', ($currentMonth % 12) + 1)
            ->setParameter('prevMonth', (($currentMonth-1) % 12) + 1);
        $r = $qb->executeQuery()->fetchAllAssociative();

        /** @noinspection SqlWithoutWhere */
        $this->getDatabase()->query("UPDATE layer SET layer_melupdate=0");
        return array_column($r, 'layer_name');
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
        /** @noinspection SqlWithoutWhere */
        $this->getDatabase()->query(
            'UPDATE game SET game_mel_lastmonth=game_currentmonth, game_mel_lastupdate=UNIX_TIMESTAMP(NOW(6))'
        );
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
     * @throws Exception
     * @api {POST} /mel/GeometryExportName Geometry Export Name
     * @apiParam {string} layer name to return the geometry data for
     * @apiParam {int} layer_type type within the layer to return. -1 for all types.
     * @apiParam {bool} construction_only whether to return data only if it's being constructed.
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
            $layer_type == -1 ? null : $layer_type,
            $construction_only,
            $policy_filters
        );
    }

    /**
     * @param string $name
     * @param int|null $geometryFilterTpe
     * @param bool $constructionOnly
     * @param object|null $policyFilters
     *
     * @return array|null
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function exportAllGeometryFromLayer(
        string $name,
        ?int $geometryFilterTpe = null,
        bool $constructionOnly = false,
        ?object $policyFilters = null
    ): ?array {
        $conn = ConnectionManager::getInstance()->getGameSessionEntityManager($this->getGameSessionId());
        $qb = $conn->createQueryBuilder();
        /** @var ?\App\Entity\Layer $layer */
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

        $q = $qb->getQuery();
        /** @var \App\Entity\Layer[] $layers */
        $layers = $q->getResult();
        $geometryTypeFilter = $geometryFilterTpe === -1 ? null : $geometryFilterTpe;
        $result["geotype"] = $layerGeoType ?->value ?? ''; // enum to string;
        $result["geometry"] = [];
        foreach ($layers as $l) {
            // export each geometry linked to the layer
            foreach ($l->getGeometry() as $geom) {
                $this->exportGeometryTo($geom, $geometryTypeFilter, $policyFilters, $result["geometry"]);
            }
        }
        $result['logs'] = json_encode($this->getLogMessages());
        return $result;
    }


    /**
     * @param Geometry $geometry
     * @param PolicyDataBase|ItemsPolicyDataBase $policyData
     * @param bool $addGeometryToResult if the caller should add the geometry to the result.
     *  If not, the policy prevents it
     * @param int|null $geometryTypeFilter
     * @return array|null
     */
    private function applyGeometryPolicy(
        Geometry                           $geometry,
        PolicyDataBase|ItemsPolicyDataBase $policyData,
        bool                               &$addGeometryToResult,
        ?int                               $geometryTypeFilter = null
    ): ?array {
        if ($policyData instanceof BufferZonePolicyData) {
            return $this->applyGeometryBufferZonePolicy(
                $geometry,
                $policyData,
                $addGeometryToResult,
                $geometryTypeFilter
            );
        }
        return null;
    }

    public function applyGeometryBufferZonePolicy(
        Geometry             $geom,
        BufferZonePolicyData $policyData,
        bool                 &$addGeomToResult,
        ?int                 $geometryTypeFilter = null
    ): ?array {
        if ($geometryTypeFilter === null) {
            return null; // mpa should always have a geometry type filter
        }
        $this->log('applied policy '.$policyData->getPolicyTypeName()->value.' with : '.$policyData->radius.'.');

        // todo(MH) there is probably a way to get it working with
        //   ConnectionManager::getInstance()->getCachedServerManagerDbConnection()->getNativeConnection()
        $this->pdo ??= new \PDO("mysql:host=$_ENV[DATABASE_HOST];port=$_ENV[DATABASE_PORT]", 'root', '');
        $geomTypeMatch = array_reduce(
            $geom->getGeometryTypes(),
            fn($carry, $item) => $carry || ($item & $geometryTypeFilter),
            false
        );
        // convert our geometry using mariadb's GIS features
        try {
            if ($geomTypeMatch) {
                $addGeomToResult = false;
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
     * @param int|null $geometryTypeFilter
     * @param object|null $policyFilters
     * @param array $exportResult
     * @throws Exception
     */
    private function exportGeometryTo(
        Geometry $geometry,
        ?int $geometryTypeFilter,
        ?object $policyFilters,
        array &$exportResult,
    ): void {
        $data = $geometry->getGeometryDataAsJsonDecoded();
        foreach (($data->policies ?? []) as $policyData) {
            $this->log('Encountered policies for geometry: '.($geometry->getName() ?? 'unnamed'));
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
            $addGeomToResult = true;
            if (null !== $geometryResult = $this->applyGeometryPolicy(
                $geometry,
                $policyData,
                $addGeomToResult,
                $geometryTypeFilter
            )) {
                $exportResult[] = $geometryResult;
            }
            if (!$addGeomToResult) {
                $this->log('Policy does not allow this geometry to be added to the result');
                return;
            }
        }
        $this->addGeometryToResult($geometry, $exportResult, $geometryTypeFilter);
    }

    private function addGeometryToResult(
        Geometry $geom,
        array    &$result,
        ?int     $geometryTypeFilter = null
    ): void {
        // this geometry is not matching the type requested, so skip it
        if ($geometryTypeFilter !== null && !in_array($geometryTypeFilter, $geom->getGeometryTypes())) {
            return;
        }
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
