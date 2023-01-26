<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\API\v1\PolicyType;
use App\Domain\Common\CommonBase;
use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use function App\await;
use function App\parallel;
use function App\tpf;

class PlanLatest extends CommonBase
{
    /**
     * initially, ask for all from time 0 to load in all user created data
     *
     * @throws Exception
     * @noinspection SpellCheckingInspection
     * @return array|PromiseInterface
     */
    public function latest(int $lastupdate)/*: array|PromiseInterface // <-- php 8 */
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        //get all plans that have changed
        $promise = $this->getAsyncDatabase()->query(
            $qb
                ->select(
                    'plan_id as id',
                    'plan_country_id as country',
                    'plan_name as name',
                    'plan_description as description',
                    'plan_gametime as startdate',
                    'plan_state as state',
                    'plan_previousstate as previousstate',
                    'plan_lastupdate as lastupdate',
                    'plan_country_id as country',
                    'plan_lock_user_id as locked',
                    'plan_active as active',
                    'plan_type as type',
                    'plan_energy_error as energy_error',
                    'plan_alters_energy_distribution as alters_energy_distribution'
                )
                ->from('plan')
                ->where('plan_lastupdate >= ' . $qb->createPositionalParameter($lastupdate))
                ->andWhere('plan_active = ' . $qb->createPositionalParameter(1))
        )
        ->then(function (Result $result) {
            $plans = $result->fetchAllRows();
            $toPromiseFunctions = [];
            foreach ($plans as $key => &$d) {
                //all layers, this is needed to merge them with geometry later
                $toPromiseFunctions['layers' . $key] = tpf(function () use ($d) {
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    return $this->getAsyncDatabase()->query(
                        $qb
                            ->select(
                                'pl.plan_layer_layer_id as layerid',
                                'l.layer_original_id as original',
                                'pl.plan_layer_state as state'
                            )
                            ->from('plan_layer', 'pl')
                            ->leftJoin('pl', 'layer', 'l', 'pl.plan_layer_layer_id=l.layer_id')
                            ->where('pl.plan_layer_plan_id = ' . $qb->createPositionalParameter($d['id']))
                    );
                });

                //energy grids
                $toPromiseFunctions['grids' . $key] = tpf(function () use ($d) {
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    return $this->getAsyncDatabase()->query(
                        $qb
                            ->select(
                                'grid_id as id',
                                'grid_name as name',
                                'grid_active as active',
                                'grid_persistent as persistent',
                                'grid_distribution_only as distribution_only',
                            )
                            ->from('grid')
                            ->where('grid_plan_id = ' . $qb->createPositionalParameter($d['id']))
                    );
                });

                //load deleted grid ids here TODO
                $toPromiseFunctions['deleted' . $key] = tpf(function () use ($d) {
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    return $this->getAsyncDatabase()->query(
                        $qb
                            ->select('grid_removed_grid_persistent as grid_persistent')
                            ->from('grid_removed')
                            ->where('grid_removed_plan_id = ' . $qb->createPositionalParameter($d['id']))
                    );
                });

                //fishing - Return NULL in the 'fishing' values when there's no values available.
                $toPromiseFunctions['fishing' . $key] = tpf(function () use ($d) {
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    return $this->getAsyncDatabase()->query(
                        $qb
                            ->select(
                                'fishing_country_id as country_id',
                                'fishing_type as type',
                                'fishing_amount as amount'
                            )
                            ->from('fishing')
                            ->where('fishing_plan_id = ' . $qb->createPositionalParameter($d['id']))
                    );
                });

                $toPromiseFunctions['votes' . $key] = tpf(function () use ($d) {
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    return $this->getAsyncDatabase()->query(
                        $qb
                            ->select(
                                'approval_country_id as country',
                                'approval_vote as vote'
                            )
                            ->from('approval')
                            ->where('approval_plan_id = ' . $qb->createPositionalParameter($d['id']))
                    );
                });

                //Restriction area settings that have changed in this plan.
                $toPromiseFunctions['restriction_settings' . $key] = tpf(function () use ($d) {
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    return $this->getAsyncDatabase()->query(
                        $qb
                            ->select(
                                'plan_restriction_area_layer_id as layer_id',
                                'plan_restriction_area_country_id as team_id',
                                'plan_restriction_area_entity_type as entity_type_id',
                                'plan_restriction_area_size as restriction_size',
                            )
                            ->from('plan_restriction_area')
                            ->where('plan_restriction_area_plan_id = ' . $qb->createPositionalParameter($d['id']))
                    );
                });
            }
            unset($d);
            return parallel($toPromiseFunctions)
                ->then(function (array $results) use (&$plans) {
                    /** @var Result[] $results */
                    $toPromiseFunctions = [];
                    foreach ($plans as $pKey => &$d) {
                        $d['layers'] = $results['layers' . $pKey]->fetchAllRows();
                        $d['grids'] = collect($results['grids' . $pKey]->fetchAllRows())
                            // fail-safe. grid persistent field should be int. If not, remove the grid.
                            ->filter(function ($value, $key) {
                                return ctype_digit((string)$value['persistent']);
                            })
                            ->all();

                        foreach ($d['grids'] as $gKey => $g) {
                            $gridId = $g['id'];
                            $toPromiseFunctions['energy'.$pKey.'-'.$gKey] = tpf(function () use ($gridId) {
                                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                                return $this->getAsyncDatabase()->query(
                                    $qb
                                        ->select(
                                            'grid_energy_country_id as country_id',
                                            'grid_energy_expected as expected'
                                        )
                                        ->from('grid_energy')
                                        ->where('grid_energy_grid_id = ' . $qb->createPositionalParameter($gridId))
                                );
                            });
                            $toPromiseFunctions['sources'.$pKey.'-'.$gKey] = tpf(function () use ($gridId) {
                                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                                return $this->getAsyncDatabase()->query(
                                    $qb
                                        ->select('grid_source_geometry_id as geometry_id')
                                        ->from('grid_source')
                                        ->where('grid_source_grid_id = ' . $qb->createPositionalParameter($gridId))
                                );
                            });
                            $toPromiseFunctions['sockets'.$pKey.'-'.$gKey] = tpf(function () use ($gridId) {
                                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                                return $this->getAsyncDatabase()->query(
                                    $qb
                                        ->select(
                                            'grid_socket_geometry_id as geometry_id'
                                        )
                                        ->from('grid_socket')
                                        ->where('grid_socket_grid_id = ' . $qb->createPositionalParameter($gridId))
                                );
                            });
                        }
                        unset($g);

                        $deleted = $results['deleted' . $pKey]->fetchAllRows();
                        $d['deleted_grids'] = array();
                        foreach ($deleted as $del) {
                            $d['deleted_grids'][] = $del['grid_persistent'];
                        }

                        $fishingValues = $results['fishing' . $pKey]->fetchAllRows();
                        if (count($fishingValues) > 0) {
                            $d['fishing'] = $fishingValues;
                        }

                        $d['votes'] = $results['votes' . $pKey]->fetchAllRows();
                        $d['restriction_settings'] = $results['restriction_settings' . $pKey]->fetchAllRows();
                    }
                    unset($d);
                    return parallel($toPromiseFunctions)
                        ->then(function (array $results) use (&$plans) {
                            foreach ($plans as $pKey => &$d) {
                                foreach ($d['grids'] as $gKey => &$g) {
                                    /** @var Result[] $results */
                                    $g['energy'] = $results['energy'.$pKey.'-'.$gKey]->fetchAllRows();
                                    $g['sources'] = $results['sources'.$pKey.'-'.$gKey]->fetchAllRows();
                                    $g['sockets'] = $results['sockets'.$pKey.'-'.$gKey]->fetchAllRows();
                                }
                                unset($g);
                            }
                            unset($d);
                            $this->formatPlans($plans);
                            return $plans;
                        });
                });
        });
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * new format since new UI style (2022-11-24), see MSP-4142
     *
     * @param array $plans
     * @return void
     */
    private function formatPlans(array &$plans): void
    {
        $plans = collect($plans)
            ->map(function ($plan) {
                $type = $plan['type'];
                unset($plan['type']);

                // PolicyUpdateEnergyPlan
                if (($type & PolicyType::ENERGY) === PolicyType::ENERGY) {
                    $policy['policy_type'] = 'energy';
                    $policy['alters_energy_distribution'] = $plan['alters_energy_distribution'];
                    $policy['grids'] = $plan['grids'];
                    $policy['deleted_grids'] = $plan['deleted_grids'];
                    $policy['energy_error'] = $plan['energy_error'];
                    $plan['policies'][] = $policy;
                }
                unset(
                    $policy,
                    $plan['alters_energy_distribution'],
                    $plan['grids'],
                    $plan['deleted_grids'],
                    $plan['energy_error']
                );

                // PolicyUpdateFishingPlan
                if (($type & PolicyType::FISHING) === PolicyType::FISHING) {
                    $policy['policy_type'] = 'fishing';
                    // PolicyUpdateEnergyPlan
                    if (!empty($plan['fishing'])) {
                        $policy['fishing'] = $plan['fishing'];
                    }
                    $plan['policies'][] = $policy;
                }
                unset($policy, $plan['fishing']);

                // PolicyUpdateShippingPlan
                if (($type & PolicyType::SHIPPING) === PolicyType::SHIPPING) {
                    $policy['policy_type'] = 'shipping';
                    // PolicyUpdateEnergyPlan
                    if (!empty($plan['restriction_settings'])) {
                        $policy['restriction_settings'] = $plan['restriction_settings'];
                    }
                    $plan['policies'][] = $policy;
                }
                unset($policy, $plan['restriction_settings']);
                return $plan;
            })
            ->all();
    }

    /**
     * @throws Exception
     * @return array|PromiseInterface
     */
    public function getMessages(float $time)/*: array|PromiseInterface // <-- php 8 */
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $promise = $this->getAsyncDatabase()->query(
            $qb
                ->select(
                    'plan_message_id as message_id',
                    'plan_message_text as message',
                    'plan_message_plan_id as plan_id',
                    'plan_message_country_id as team_id',
                    'plan_message_user_name as user_name',
                    "FROM_UNIXTIME(plan_message_time, '%b %d %H:%i') as time"
                )
                ->from('plan_message')
                ->where('plan_message_time>' . $qb->createPositionalParameter($time))
                ->orderBy('plan_message_time')
        )
        ->then(function (Result $result) {
            return $result->fetchAllRows();
        });
        return $this->isAsync() ? $promise : await($promise);
    }
}
