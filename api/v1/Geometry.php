<?php

namespace App\Domain\API\v1;

use Drift\DBAL\Result;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\tpf;
use function App\await;

class Geometry extends Base
{
    private const ALLOWED = array(
        "Post",
        "PostSubtractive",
        "Update",
        "Data",
        "Delete",
        "MarkForDelete",
        "UnmarkForDelete"
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {post} /geometry/post Post
     * @apiDescription Create a new geometry entry in a plan
     * @apiParam {int} layer id of layer to post in
     * @apiParam {string} geometry string of geometry to post
     * @apiParam {int} plan id of the plan
     * @apiParam {string} FID (optional) FID of geometry
     * @apiParam {int} persistent (optional) persistent ID of geometry
     * @apiParam {string} data (optional) meta data string of geometry object
     * @apiParam {int} country (optional) The owning country id. NULL or -1 if no country is set.
     * @apiSuccess {int} id of the newly created geometry
     * @return int|PromiseInterface
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Post(
        int $layer,
        string $geometry,
        string $FID = "",
        int $persistent = null,
        string $data = "",
        int $country = null,
        int $plan = -1
    ): int|PromiseInterface {
        if ($country == -1) {
            $country = null;
        }

        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->insert('geometry')
                ->values([
                    'geometry_layer_id' => $qb->createPositionalParameter($layer),
                    'geometry_geometry' => $qb->createPositionalParameter($geometry),
                    'geometry_FID' => $qb->createPositionalParameter($FID),
                    'geometry_persistent' => $qb->createPositionalParameter($persistent),
                    'geometry_data' => $qb->createPositionalParameter($data),
                    'geometry_country_id' => $qb->createPositionalParameter($country)
                ])
        )
        ->done(
            function (Result $result) use ($deferred, $plan, $persistent) {
                if (null === $newId = $result->getLastInsertedId()) {
                    $deferred->reject();
                    return null;
                }

                $toPromiseFunctions = [];
                if ($plan != -1) {
                    $toPromiseFunctions[] = tpf(function () use ($plan) {
                        $qb = $this->getAsyncDatabase()->createQueryBuilder();
                        return $this->getAsyncDatabase()->query(
                            $qb
                                ->update('plan')
                                ->set('plan_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
                                ->where($qb->expr()->eq('plan_id', $qb->createPositionalParameter($plan)))
                        );
                    });
                }
                if (null == $persistent) {
                    $toPromiseFunctions[] = tpf(function () use ($newId) {
                        $qb = $this->getAsyncDatabase()->createQueryBuilder();
                        return $this->getAsyncDatabase()->query(
                            $qb
                                ->update('geometry')
                                ->set('geometry_persistent', $qb->createPositionalParameter($newId))
                                ->where($qb->expr()->eq('geometry_id', $qb->createPositionalParameter($newId)))
                        );
                    });
                }

                if (empty($toPromiseFunctions)) {
                    $deferred->resolve($newId);
                    return null;
                }

                parallel($toPromiseFunctions)
                    ->done(
                        function () use ($deferred, $newId) {
                            $deferred->resolve($newId);
                        },
                        function ($reason) use ($deferred) {
                            $deferred->reject($reason);
                        }
                    );
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );

        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/PostSubtractive Post Subtractive
     * @apiDescription Create a new subtractive polygon on an existing polygon
     * @apiParam {int} layer id of layer to post in
     * @apiParam {string} geometry string of geometry to post
     * @apiParam {int} subtractive id of the polygon the newly created polygon is subtractive to
     * @apiParam {string} FID (optional) FID of geometry
     * @apiParam {string} persistent (optional) persistent ID of geometry
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function PostSubtractive(
        int $layer,
        string $geometry,
        int $subtractive,
        int $persistent = null,
        string $FID = ""
    ): ?PromiseInterface {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->insert('geometry')
                ->values([
                    'geometry_layer_id' => $qb->createPositionalParameter($layer),
                    'geometry_geometry' => $qb->createPositionalParameter($geometry),
                    'geometry_FID' => $qb->createPositionalParameter($FID),
                    'geometry_persistent' => $qb->createPositionalParameter($persistent),
                    'geometry_subtractive' => $qb->createPositionalParameter($subtractive)
                ])
        )
        ->then(function (Result $result) use ($persistent) {
            if (null === $id = $result->getLastInsertedId()) {
                throw new Exception('Could not retrieve last inserted id');
            }
            //set the persistent id if it's new geometry
            if (null === $persistent) {
                return $this->getAsyncDatabase()->update(
                    'geometry',
                    [
                        'geometry_id' => $id
                    ],
                    [
                        'geometry_persistent' => $id
                    ]
                );
            }
            return null; // nothing to do.
        })
        ->done(
            function (/* ?Result $result */) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/update Update
     * @apiParam {int} id geometry id to update
     * @apiParam {string} geometry string of geometry json to post
     * @apiParam {int} country country id to set as geometry's owner
     * @apiSuccess {int} id same geometry id
     * @return int|PromiseInterface
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Update(int $id, int $country, string $geometry): int|PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('geometry')
                ->set('geometry_geometry', $qb->createPositionalParameter($geometry))
                ->set('geometry_country_id', $qb->createPositionalParameter($country))
                ->where($qb->expr()->eq('geometry_id', $qb->createPositionalParameter($id)))
        )
        ->done(
            function (Result $result) use ($deferred, $id) {
                $deferred->resolve($id);
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/Data Data
     * @apiDescription Adjust geometry metadata and type
     * @apiParam {int} id geometry id to update
     * @apiParam {string} data metadata of the geometry to set
     * @apiParam {string} type type value, either single integer or comma-separated multiple integers
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Data(string $data, string $type, int $id): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('geometry')
                ->set('geometry_data', $qb->createPositionalParameter($data))
                ->set('geometry_type', $qb->createPositionalParameter($type))
                ->where($qb->expr()->eq('geometry_id', $qb->createPositionalParameter($id)))
        )
        ->done(
            function (Result $result) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }


    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/Delete Delete
     * @apiDescription Delete geometry without using a plan
     * @apiParam {int} id geometry id to delete, marks a row as inactive
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Delete(int $id): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('geometry')
                ->set('geometry_active', '0')
                ->set('geometry_deleted', '1')
                ->where(
                    $qb->expr()->or(
                        $qb->expr()->eq('geometry_id', $qb->createPositionalParameter($id))
                    )
                    ->with($qb->expr()->eq('geometry_subtractive', $qb->createPositionalParameter($id)))
                )
        )
        ->done(
            function (/* Result $result */) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/MarkForDelete MarkForDelete
     * @apiDescription Delete geometry using a plan, this will be triggered at the execution time of a plan
     * @apiParam {int} id geometry persistent id to delete
     * @apiParam {int} plan plan id where the geometry will be deleted
     * @apiParam {int} layer the layer id where the geometry will be deleted
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function MarkForDelete(int $plan, int $id, int $layer): ?PromiseInterface
    {
        $deferred = new Deferred();
        $this->getAsyncDatabase()->insert('plan_delete', [
            'plan_delete_plan_id' => $plan,
            'plan_delete_geometry_persistent' => $id,
            'plan_delete_layer_id' => $layer
        ])
        ->done(
            function (/* Result $result */) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/UnmarkForDelete UnmarkForDelete
     * @apiDescription Remove the deletion of a geometry put in the plan
     * @apiParam {int} id geometry persistent id to undelete
     * @apiParam {int} plan plan id where the geometry is located in
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UnmarkForDelete(int $plan, int $id): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->delete('plan_delete')
                ->where(
                    $qb->expr()->and(
                        $qb->expr()->eq('plan_delete_plan_id', $qb->createPositionalParameter($plan))
                    )
                    ->with($qb->expr()->eq('plan_delete_geometry_persistent', $qb->createPositionalParameter($id)))
                )
        )
        ->done(
            function (/* Result $result */) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    public function processAndAdd($feature, $layerId, $layerMetaData): bool
    {
        $feature = $this->moveDataFromArray($layerMetaData, $feature);
        if ($this->featureHasUnknownType($layerMetaData, $feature)) {
            throw new \Exception(
                'Importing geometry '.$feature['id'].' for layer '.$layerMetaData['layer_name'].
                ' with type '.$feature['properties_msp']['type'].', but this type has not been defined in the 
                session config file, so not continuing.'
            );
        }

        $geometryData = $feature["geometry"];
        // let's make sure we are always working with multidata: multipolygon, multilinestring, multipoint
        if ($geometryData["type"] == "Polygon"
            || $geometryData["type"] == "LineString"
            ||  $geometryData["type"] == "Point"
        ) {
            $geometryData["coordinates"] = [$geometryData["coordinates"]];
            $geometryData["type"] = "Multi".$geometryData["type"];
        }

        $encodedFeatureProperties = json_encode($feature["properties"]);
        if (strcasecmp($geometryData["type"], "MultiPolygon") == 0) {
            foreach ($geometryData["coordinates"] as $multi) {
                if (!is_array($multi)) {
                    continue;
                }
                $returnChecks[] = $this->addMultiPolygon(
                    $multi,
                    $layerId,
                    $encodedFeatureProperties,
                    $feature['properties_msp']['countryId'],
                    $feature['properties_msp']['type'],
                    $feature['properties_msp']['mspId'],
                    $layerMetaData['layer_name']
                );
            }
            return (!array_search(false, $returnChecks ?? [], true));
        }
        if (strcasecmp($geometryData["type"], "MultiPoint") == 0) {
            return (!is_null($this->addRow(
                [
                    'geometry_layer_id' => $layerId,
                    'geometry_geometry' => json_encode($geometryData["coordinates"]),
                    'geometry_data' => $encodedFeatureProperties,
                    'geometry_country_id' => $feature['properties_msp']['countryId'],
                    'geometry_type' => $feature['properties_msp']['type'],
                    'geometry_mspid' => $feature['properties_msp']['mspId'],
                    'geometry_subtractive' => 0
                ],
                $layerMetaData['layer_name']
            )));
        }
        if (strcasecmp($geometryData["type"], "MultiLineString") == 0) {
            foreach ($geometryData["coordinates"] as $line) {
                $returnChecks2[] = $this->addRow(
                    [
                        'geometry_layer_id' => $layerId,
                        'geometry_geometry' => json_encode($line),
                        'geometry_data' => $encodedFeatureProperties,
                        'geometry_country_id' => $feature['properties_msp']['countryId'],
                        'geometry_type' => $feature['properties_msp']['type'],
                        'geometry_mspid' => $feature['properties_msp']['mspId'],
                        'geometry_subtractive' => 0
                    ],
                    $layerMetaData['layer_name']
                );
            }
            return (!array_search(null, $returnChecks2 ?? [], true));
        }
        return false;
    }

    public function moveDataFromArray(
        array $layerMetaData,
        array $feature
    ): array {
        $featureProperties = $feature['properties'];
        if (!empty($layerMetaData["layer_property_as_type"])) {
            // check if the layer_property_as_type value exists in $featureProperties
            $type = '-1';
            if (!empty($featureProperties[$layerMetaData["layer_property_as_type"]])) {
                $featureTypeProperty = $featureProperties[$layerMetaData["layer_property_as_type"]];
                foreach ($layerMetaData["layer_type"] as $layerTypeMetaData) {
                    if (!empty($layerTypeMetaData["map_type"])) {
                        // identify the 'other' category
                        if (strtolower($layerTypeMetaData["map_type"]) == "other") {
                            $typeOther = $layerTypeMetaData["value"];
                        }
                        // translate the found $featureProperties value to the type value
                        if ($layerTypeMetaData["map_type"] == $featureTypeProperty) {
                            $type = $layerTypeMetaData["value"];
                            break;
                        }
                    }
                }
            }
            if ($type == -1) {
                $type = $typeOther ?? 0;
            }
        } else {
            $type = (int)($featureProperties['type'] ?? 0);
            unset($featureProperties['type']);
        }

        if (isset($featureProperties['mspid'])
            && is_numeric($featureProperties['mspid'])
            && intval($featureProperties['mspid']) !== 0
        ) {
            $mspId = intval($featureProperties['mspid']);
            unset($featureProperties['mspid']);
        }

        if (isset($featureProperties['country_id'])
            && is_numeric($featureProperties['country_id'])
            && intval($featureProperties['country_id']) !== 0
        ) {
            $countryId = intval($featureProperties['country_id']);
            unset($featureProperties['country_id']);
        }

        $feature['properties'] = $featureProperties;
        $feature['properties_msp']['type'] = $type;
        $feature['properties_msp']['mspId'] = $mspId ?? null;
        $feature['properties_msp']['countryId'] = $countryId ?? null;
        return $feature;
    }

    public function featureHasUnknownType(array $layerMetaData, array $feature): bool
    {
        return (!isset($layerMetaData['layer_type'][$feature['properties_msp']['type']]));
    }

    private function addMultiPolygon(
        array $multi,
        int $layerId,
        string $jsonData,
        ?int $countryId,
        string $type,
        ?int $mspId,
        string $layerName
    ): bool {
        $lastId = 0;
        for ($j = 0; $j < sizeof($multi); $j++) {
            if (sizeof($multi) > 1 && $j != 0) {
                //this is a subtractive polygon
                $this->addRow(
                    [
                        'geometry_layer_id' => $layerId,
                        'geometry_geometry' => json_encode($multi[$j]),
                        'geometry_data' => $jsonData,
                        'geometry_country_id' => $countryId,
                        'geometry_type' => $type,
                        'geometry_mspid' => null,
                        'geometry_subtractive' => $lastId
                    ],
                    $layerName
                );
            } else {
                $lastId = $this->addRow(
                    [
                        'geometry_layer_id' => $layerId,
                        'geometry_geometry' => json_encode($multi[$j]),
                        'geometry_data' => $jsonData,
                        'geometry_country_id' => $countryId,
                        'geometry_type' => $type,
                        'geometry_mspid' => $mspId,
                        'geometry_subtractive' => 0
                    ],
                    $layerName
                );
            }
        }
        return false;
    }

    public function findPlayArea(): string|null
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return await(
            $this->getAsyncDatabase()->query(
                $qb->select('g.geometry_geometry')
                    ->from('geometry', 'g')
                    ->leftJoin('g', 'layer', 'l', 'g.geometry_layer_id = l.layer_id')
                    ->where($qb->expr()->like(
                        'l.layer_name',
                        $qb->createPositionalParameter('_PLAYAREA%')
                    ))
            )->then(function (Result $result) {
                return $result->fetchFirstRow()['geometry_geometry'] ?? null;
            })
        );
    }

    private function addRow(array $geometryColumns, $layerName = ''): int|null
    {
        if (empty($geometryColumns['geometry_geometry'])
            || empty($geometryColumns['geometry_layer_id'])
        ) {
            throw new \Exception('Need at least some geometry and a layer ID to continue.');
        }
        $subtractive = $geometryColumns['geometry_subtractive'] ?? 0;
        if ($subtractive === 0 && empty($geometryColumns['geometry_mspid'])) {
            // so many algorithms to choose from, but this one seemed to have low collision, reasonable speed,
            //   and simply availability to PHP in default installation
            $algo = 'fnv1a64';
            // to avoid duplicate MSP IDs, we need the string to include the layer name, the geometry, and if available
            //   the geometry's name ... there have been cases in which one layer had exactly the same geometry twice
            //   to indicate two different names given to that area... very annoying
            $dataToHash = $layerName.$geometryColumns['geometry_geometry'];
            $dataArray = json_decode($geometryColumns['geometry_data'], true);
            $dataToHash .= $dataArray['name'] ?? '';
            $geometryColumns['geometry_mspid'] = hash($algo, $dataToHash);
        }
        return $this->insertRowIntoTable('geometry', $geometryColumns);
    }

    /**
     * Returns the database id of the persistent geometry id described by the base_geometry_info
     *
     */
    public function fixupPersistentGeometryID(array $baseGeometryInfo, array $mappedGeometryIds): int|string
    {
        $fixedGeometryId = -1;
        if (!empty($baseGeometryInfo["geometry_mspid"])) {
            $fixedGeometryId = $this->getGeometryIdByMspId($baseGeometryInfo["geometry_mspid"]);
        } else {
            if (array_key_exists($baseGeometryInfo["geometry_persistent"], $mappedGeometryIds)) {
                $fixedGeometryId = $mappedGeometryIds[$baseGeometryInfo["geometry_persistent"]];
            } else {
                $return = "Found geometry ID (Fallback field \"geometry_persistent\": ".
                    $baseGeometryInfo["geometry_persistent"].
                    ") which is not referenced by msp id and hasn't been imported by the plans importer yet. ".
                    var_export($baseGeometryInfo, true);
            }
        }
        return $return ?? $fixedGeometryId;
    }

    /**
     * Returns the database id of the geometry id described by the base_geometry_info
     *
     */
    public function fixupGeometryID(array $baseGeometryInfo, array $mappedGeometryIds): int|string
    {
        $fixedGeometryId = -1;
        if (array_key_exists($baseGeometryInfo["geometry_id"], $mappedGeometryIds)) {
            $fixedGeometryId = $mappedGeometryIds[$baseGeometryInfo["geometry_id"]];
        } else {
            // If we can't find the geometry id in the ones that we already have imported, check if the geometry id
            //   matches the persistent id, and if so select it by the mspid since this should all be present then.
            if ($baseGeometryInfo["geometry_id"] == $baseGeometryInfo["geometry_persistent"]) {
                if (isset($baseGeometryInfo["geometry_mspid"])) {
                    $fixedGeometryId = $this->getGeometryIdByMspId($baseGeometryInfo["geometry_mspid"]);
                } else {
                    $return = "Found geometry (".implode(", ", $baseGeometryInfo).
                        " which has not been imported by the plans importer. The persistent id matches but mspid is".
                        "not set.";
                }
            } else {
                $return = "Found geometry ID (Fallback field \"geometry_id\": ". $baseGeometryInfo["geometry_id"].
                    ") which hasn't been imported by the plans importer yet.";
            }
        }
        return $return ?? $fixedGeometryId;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function getGeometryIdByMspId(int|string $mspId): int|string
    {
        $return = $this->selectRowsFromTable('geometry', ['geometry_mspid' => $mspId])['geometry_id'];
        if (is_null($return)) {
            return 'Could not find MSP ID ' . $mspId . ' in the current database';
        }
        return (int) $return;
    }
}
