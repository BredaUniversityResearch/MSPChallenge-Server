<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\API\v1\Base;
use App\Domain\Common\CommonBase;
use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\tpf;

class LayerLatest extends CommonBase
{
    // ----------------------------------
    // internal methods
    // ----------------------------------
    /**
     * @throws Exception
     * @noinspection PhpUnusedParameterInspection
     */
    public function latest(array $layers, float $time, int $planId): PromiseInterface
    {
        // get all the geometry of a plan, excluding the geometry that has been deleted in the current plan, or has
        //   been replaced by a newer generation (so the highest geometry_id of any persistent ID)
        $toPromiseFunctions = [];
        foreach ($layers as $key => $layer) {
            $toPromiseFunctions[$key] = tpf(function () use ($layer) {
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $this->getAsyncDatabase()->query(
                    $qb
                        ->select(
                            'geometry_id as id',
                            'geometry_geometry as geometry',
                            'geometry_country_id as country',
                            'geometry_FID as FID',
                            'geometry_data as data',
                            'geometry_layer_id as layer',
                            'geometry_active as active',
                            'geometry_subtractive as subtractive',
                            'geometry_type as type',
                            'geometry_persistent as persistent',
                            'geometry_mspid as mspid',
                        )
                        ->from('geometry')
                        ->where(
                            $qb->expr()->and(
                                'geometry_layer_id = ?',
                                'geometry_deleted = 0',
                                $qb->expr()->notIn(
                                    'geometry_id',
                                    '
                                    SELECT plan_delete.plan_delete_geometry_persistent
                                    FROM plan_delete
                                    WHERE plan_delete.plan_delete_geometry_persistent = geometry_id
                                      AND plan_delete.plan_delete_layer_id = ?',
                                ),
                                $qb->expr()->in(
                                    '(geometry_id, geometry_persistent)',
                                    '
                                    SELECT MAX(geometry_id), geometry_persistent
                                    FROM geometry
                                    WHERE geometry_layer_id = ?
                                    GROUP BY geometry_persistent',
                                )
                            )
                        )
                        ->orderBy('geometry_FID, geometry_subtractive')
                        ->setParameters(array_fill(0, 3, $layer['layerid']))
                );
            });
        }
        $toPromiseFunctions['deleted'] = tpf(function () use ($planId) {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select(
                        'pd.plan_delete_geometry_persistent as geometry',
                        'pd.plan_delete_layer_id as layerid',
                        'l.layer_original_id as original'
                    )
                    ->from('plan_delete', 'pd')
                    ->leftJoin('pd', 'layer', 'l', 'pd.plan_delete_layer_id=l.layer_id')
                    ->where('pd.plan_delete_plan_id = ' . $qb->createPositionalParameter($planId))
                    ->orderBy('pd.plan_delete_layer_id')
            );
        });
        return parallel($toPromiseFunctions)
            ->then(function (array $results) use ($layers) {
                /** @var Result[] $results */
                foreach ($layers as $key => $layer) {
                    $layers[$key]['geometry'] = $results[$key]->fetchAllRows();
                    $layers[$key]['geometry'] = Base::MergeGeometry($layers[$key]['geometry']);
                }
                $deleted = $results['deleted']->fetchAllRows();
                foreach ($deleted as $del) {
                    $found = false;

                    foreach ($layers as &$layer) {
                        if (isset($layer['layerid']) && $del['layerid'] == $layer['layerid']) {
                            if (!isset($layer['deleted'])) {
                                $layer['deleted'] = array();
                            }
                            $layer['deleted'][] = $del['geometry'];
                            $found = true;
                            break;
                        }
                    }
                    unset($layer);

                    if (!$found) {
                        $layers[] = [];
                        $layers[sizeof($layers)-1]['deleted'] = array(
                            'layerid' => $del['layerid'],
                            'original' => $del['original'],
                            'deleted' => array($del['geometry'])
                        );
                    }
                }
                return $layers;
            });
    }

    /**
     * @throws Exception
     */
    public function latestRaster(float $time): PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
            $qb
                ->select(
                    'layer_raster as raster',
                    'layer_id as id'
                )
                ->from('layer')
                ->where('layer_geotype = ' . $qb->createPositionalParameter('raster'))
                ->andWhere('layer_lastupdate > ' . $qb->createPositionalParameter($time))
        );
    }
}
