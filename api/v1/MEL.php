<?php

namespace App\Domain\API\v1;

use App\Domain\Common\EntityEnums\PolicyTypeDataType;
use App\Domain\Services\ConnectionManager;
use App\Entity\PlanLayer;
use App\Entity\PlanPolicy;
use App\Entity\Policy;
use App\Entity\PolicyFilter;
use App\Entity\PolicyFilterLink;
use Doctrine\ORM\AbstractQuery;
use Exception;

class MEL extends Base
{
    private const ALLOWED = array(
        "OnReimport",
        "Config",
        "UpdateLayer",
        "ShouldUpdate",
        "Update",
        "TickDone",
        "GetFishing",
        "GeometryExportName",
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

    public function getFishingPolicySettings(): array
    {
        $mel = $this->Config();
        return $mel['fishing_policy_settings'] ?? [];
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
            [PolicyType::FISHING, PolicyType::FISHING]
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
            array("", 1, -1, "IMPLEMENTED", PolicyType::FISHING),
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
                SELECT DISTINCT l.layer_original_id
                FROM layer l
                JOIN plan_layer pl ON l.layer_id = pl.plan_layer_layer_id
                JOIN plan_policy pp ON pl.plan_layer_plan_id = pp.plan_id
                JOIN policy p ON pp.policy_id = p.id
                JOIN policy_filter_link pfl ON p.id = pfl.policy_id
                JOIN policy_filter pf ON pfl.policy_filter_id = pf.id
                JOIN policy_filter_type pft ON pf.type_id = pft.id AND pft.name = 'schedule' AND (
                  (
                    JSON_SEARCH(pf.value, 'one', :prevMonth, NULL, '$[*]') IS NOT NULL
                    AND JSON_SEARCH(pf.value, 'one', :month, NULL, '$[*]') IS NULL
                  ) OR (
                    JSON_SEARCH(pf.value, 'one', :prevMonth, NULL, '$[*]') IS NULL
                    AND JSON_SEARCH(pf.value, 'one', :month, NULL, '$[*]') IS NOT NULL
                  )
               )
SUBQUERY
            ))
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
     * @todo change method name to something more descriptive, why not GetLayerData?
     *   can be layer geometry data or raster data, but also plan policies linked to that layer
     * @todo change name of this parameter to something more descriptive like $geometryTypeFilter
     * @param int $layer_type this is a filter for the layer's geometry field "geometry_type" which holds an optional
     *   comma separated string holding integers.
     * E.g. For MPAs those refer to a fleet 1,2 or 3.
     *   So if the filter is set to 1,2 or 3, it will only return geometries referring that fleet in the string
     *   1 = No Bottom Trawl Fleets
     *   2 = No Industrial and Pelagic Trawl Fleets
     *   3 = No Drift and Fixed Nets Fleets
     *
     * @apiGroup MEL
     * @apiDescription Gets all the geometry data of a layer
     * @throws Exception
     * @api {POST} /mel/GeometryExportName Geometry Export Name
     * @apiParam {string} layer name to return the geometry data for
     * @apiParam {int} layer_type type within the layer to return. -1 for all types.
     * @apiParam {bool} construction_only whether or not to return data only if it's being constructed.
     * @apiSuccess {string} JSON JSON Object
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GeometryExportName(string $name, int $layer_type = -1, bool $construction_only = false): ?array
    {
        $conn = ConnectionManager::getInstance()->getGameSessionEntityManager($this->getGameSessionId());
        $qb = $conn->createQueryBuilder();
        /** @var null|array $layer */
        $layer = $qb
            ->from('App:Layer', 'l')
            ->select('l.layerId, l.layerGeotype, l.layerRaster')
            ->where('l.layerName = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
        if (null === $layer) {
            return null;
        }

        $result = array("geotype" => "");
        $layerGeoType = $layer['layerGeotype'] ?? '';
        if ($layerGeoType == "raster") {
            $rasterJson = json_decode($layer['layerRaster'], true);
            $result["geotype"] = $layerGeoType;
            $result["raster"] = $rasterJson['url'] ?? '';
            return $result;
        }

        $layerId = $layer['layerId'] ?? 0;
        $qb = $conn->createQueryBuilder();
        $qb
            ->select('pl, l, ge, p, pp, pol, pfl, pf')
            ->from('App:PlanLayer', 'pl')
            ->leftJoin('pl.layer', 'l')
            ->leftJoin('l.geometry', 'ge')
            ->leftJoin('l.originalLayer', 'ol')
            ->leftJoin('pl.plan', 'p')
            ->leftJoin('p.planPolicies', 'pp')
            ->leftJoin('pp.policy', 'pol')
            ->leftJoin('pol.policyFilterLinks', 'pfl')
            ->leftJoin('pfl.policyFilter', 'pf')
            ->where('l.layerId = :layerId OR ol.layerId = :layerId')
            ->setParameter('layerId', $layerId)
            ->andWhere('ge.geometryActive = 1');
        if ($construction_only) {
            $qb
                ->andWhere('pl.planLayerState = :active')
                ->setParameter('active', 'ASSEMBLY');
        } else {
            $qb
                ->andWhere(
                    $qb->expr()->orX(
                        'pl.planLayerState = :active',
                        'pl.planLayerState IS NULL'
                    )
                )
                ->setParameter('active', 'ACTIVE');
        }
        $q = $qb->getQuery();
        /** @var PlanLayer[] $planLayers */
        $planLayers = $q->getResult();
        $geometryTypeFilter = $layer_type === -1 ? null : $layer_type;
        $result["geotype"] = $layerGeoType;
        $result["geometry"] = [];
        foreach ($planLayers as $planLayer) {
            // add the geometry linked to the layer to be pressed, these do not include the policy ones
            foreach ($planLayer->getLayer()->getGeometry() as $geom) {
                if ($geometryTypeFilter !== null && !in_array($geometryTypeFilter, $geom->getGeometryTypes())) {
                    continue;
                }
                $geometryPoints = $geom->getGeometryGeometry();
                if (empty($geometryPoints)) {
                    continue;
                }
                // add extra array layer.
                // needed for polygons: 1 entry is exterior ring, 2nd and onwards are interior rings
                //   only an exterior ring in this case.
                $result["geometry"][] = [json_decode($geometryPoints, true)];
            }
        }
        // currently only needed and support for polygons
        if ($layerGeoType == 'polygon') {
            $this->onGeometryExportNameResult($layer, $planLayers, $result, $geometryTypeFilter);
        }
        return $result;
    }

    /**
     * @param PlanLayer[] $planLayers
     *
     * @throws Exception
     */
    private function onGeometryExportNameResult(
        array $layer,
        array $planLayers,
        array &$result,
        ?int $geometryTypeFilter = null
    ): void {
        $result['debug-message'] ??= '';
        $result['debug-message'] .= 'hook activated for layer: '.$layer['layerId'].'.'.PHP_EOL;

        // todo(MH) there is probably a way to get it working with
        //   ConnectionManager::getInstance()->getCachedServerManagerDbConnection()->getNativeConnection()
        $pdo = new \PDO("mysql:host=$_ENV[DATABASE_HOST];port=$_ENV[DATABASE_PORT]", 'root', '');
        foreach ($planLayers as $planLayer) {
            $planPolicies = $planLayer->getPlan()->getPlanPolicies()->toArray();
            if (empty($planPolicies)) {
                continue;
            }
            $result['debug-message'] .= 'policies: '.
                implode(
                    ',',
                    array_map(fn(PlanPolicy $pp) => $pp->getPolicy()->getType()->getName(), $planPolicies)
                ).'.'.PHP_EOL;
            $planPolicies = $this->applyPolicyFilters($planPolicies, $geometryTypeFilter);
            $result['debug-message'] .= 'policies filtered: '.
                implode(
                    ',',
                    array_map(fn(PlanPolicy $pp) => $pp->getPolicy()->getType()->getName(), $planPolicies)
                ).'.'.PHP_EOL;
            foreach ($planPolicies as $planPolicy) {
                $planLayers = $planLayer->getPlan()->getPlanLayer();
                $geometry = [];
                foreach ($planLayers as $pl) {
                    /** @var \App\Entity\Geometry[] $geometry */
                    $geometry = array_merge($geometry, $pl->getLayer()->getGeometry()->toArray());
                }
                $policy = $planPolicy->getPolicy();
                if ($policy->getType()->getDataType() == PolicyTypeDataType::Boolean) {
                    // the boolean policies are just passed through to mel
                    $result["policies"][] = [
                        "name" => $planPolicy->getPolicy()->getType()->getName(),
                        "value" => json_encode((bool)$planPolicy->getPolicy()->getValue())
                    ];
                } elseif ($planPolicy->getPolicy()->getType()->getName() == 'buffer') {
                    $this->applyBufferPolicy(
                        $planPolicy->getPolicy(),
                        $geometry,
                        $result,
                        $pdo,
                        $geometryTypeFilter
                    );
                } elseif (null !== $eezPolicyFilters = self::getEEZPolicyFilters($planPolicy)) {
                    // add the geometry linked to the policy to be pressured but only if
                    //   the geometry is inside or intersecting any of the selected EEZs
                    $sql = <<<'SQL'
                    DROP FUNCTION IF EXISTS ToWkt;
                    DELIMITER $$
                    CREATE FUNCTION ToWkt(input_text TEXT) RETURNS TEXT
                    BEGIN
                      RETURN REPLACE(
                        REPLACE(
                          REPLACE(REPLACE(REPLACE(input_text, '],[', '|'),',',' '),'|',','),'[[','POLYGON(('),
                          ']]',
                        '))'
                      );
                    END;
                    $$
                    DELIMITER ;
                    SQL;
                    $pdo->query($sql);

                    foreach ($geometry as $geom) {
                        $st = $pdo->prepare(
                            <<<'SQL'
                            select count(geometry_id)
                            from `geometry` WHERE geometry_layer_id IN
                              (SELECT layer_id FROM layer WHERE layer_short = 'EEZ')
                            AND JSON_EXTRACT(geometry_data, '$.FID') IN (:ids)
                            AND ST_Intersects(
                            GeomFromText(ToWkt(geometry_geometry), 3035),
                            GeomFromText(:text, 3035)
                            )
                            SQL
                        );
                        $st->bindValue('text', self::toWkt(json_decode($geom->getGeometryGeometry(), true)));
                        $st->bindValue(
                            'ids',
                            // @todo combine all fids from the policy filters into a single array and
                            //   make a comma-separated string of them
                            '3' // for testing purposes
                            //  implode(
                            //      ',',
                            //      array_merge(
                            //          ...collect($eezPolicyFilters)->map(fn(PolicyFilter $pf) => $pf->getValue()
                            //              ->all()
                            //      )
                            //  ) ?: '0'
                        );
                        $st->execute();
                        if (false !== $st->fetchColumn()) {
                            $result["geometry"][] = [json_decode($geom->getGeometryGeometry(), true)];
                        }
                    }
                } else {
                    // add the geometry linked to the policy to be pressured
                    foreach ($geometry as $geom) {
                        $result["geometry"][] = [json_decode($geom->getGeometryGeometry(), true)];
                    }
                }
            }
        }
    }

    /**
     * @param PlanPolicy $planPolicy
     * @return PolicyFilter[]|null
     */
    private static function getEEZPolicyFilters(PlanPolicy $planPolicy): ?array
    {
        $arr = collect($planPolicy->getPolicy()->getPolicyFilterLinks())->filter(
            function (PolicyFilterLink $pfl, $k) {
                return $pfl->getPolicyFilter()->getType() == 'EEZ';
            }
        )->map(fn(PolicyFilterLink $pfl) => $pfl->getPolicyFilter())->all();
        if (empty($arr)) {
            return null;
        }
        return $arr;
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

    /**
     * @param PlanPolicy[] $planPolicies
     * @param int|null $geometryTypeFilter
     * @return PlanPolicy[]
     * @throws Exception
     */
    private function applyPolicyFilters(array $planPolicies, ?int $geometryTypeFilter = null): array
    {
        $result = [];
        $currentMonth = (new Game())->GetCurrentMonthAsId();
        foreach ($planPolicies as $planPolicy) {
            $policy = $planPolicy->getPolicy();
            if ($geometryTypeFilter !== null && false === $policy->hasFleetFiltersMatch($geometryTypeFilter)) {
                 continue; // no fleet filter matching the geometry type
            }
            if (false === $policy->hasScheduleFiltersMatch($currentMonth)) {
                 continue; // meaning there should not be a seasonal closure for this month, so no pressures
            }
            $result[] = $planPolicy;
        }
        return $result;
    }

    /**
     * @param Policy $policy
     * @param \App\Entity\Geometry[] $geometry
     * @param array $result
     * @param $pdo
     * @param int|null $geometryTypeFilter
     */
    public function applyBufferPolicy(
        Policy $policy,
        array $geometry,
        array &$result,
        $pdo,
        ?int $geometryTypeFilter = null
    ): void {
        if (empty($geometry)) {
            return;
        }
        if ($geometryTypeFilter === null) {
            return; // mpa should always have a geometry type filter
        }
        $newResultGeometry = [];
        $result['debug-message'] ??= '';
        $result['debug-message'] .= 'applied policy '.$policy->getType()->getName().' with value: '.
            $policy->getValue().'.'.PHP_EOL;
        foreach ($geometry as $geom) {
            $geomTypeMatch = array_reduce(
                $geom->getGeometryTypes(),
                fn($carry, $item) => $carry || ($item & $geometryTypeFilter),
                false
            );
            // convert our geometry using mariadb's GIS features
            try {
                if ($geomTypeMatch) {
                    // buffer including the original polygon
                    $st = $pdo->prepare(
                        <<< 'SQL'
                        SELECT AsText(BUFFER(GeomFromText(:text, 3035), :buffer))
                        SQL
                    );
                } else {
                    // buffer excluding the original polygon
                    $st = $pdo->prepare(
                        <<< 'SQL'
                        SELECT AsText(ST_SYMDIFFERENCE(
                          BUFFER(GeomFromText(:text, 3035), :buffer),
                          GeomFromText(:text, 3035)
                        ))
                        SQL
                    );
                }
                $st->bindValue('text', self::toWkt(json_decode($geom->getGeometryGeometry(), true)));
                $st->bindValue('buffer', $policy->getValue());
                $st->execute();
                // for debugging use https://wktmap.com/ to visualize using EPSG:3035 !
                $bufferedPolygonText = $st->fetchColumn();
                $newResultGeometry[] = self::fromWkt($bufferedPolygonText);
            } catch (\Exception $e) {
                while (null !== $prev = $e->getPrevious()) {
                    $e = $prev;
                }
                $result['debug-message'] .= $e->getMessage() . PHP_EOL;
                continue;
            }
        }
        if (empty($newResultGeometry)) {
            return;
        }
        $result['geometry'] = $newResultGeometry;
    }
}
