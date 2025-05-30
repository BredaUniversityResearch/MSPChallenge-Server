<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\API\v1\GeneralPolicyType;
use App\Domain\API\v1\Plan;
use App\Domain\Common\CommonBase;
use App\Domain\Common\EntityEnums\PolicyTypeName;
use App\Domain\PolicyData\PolicyDataBase;
use App\Domain\PolicyData\PolicyDataFactory;
use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use Swaggest\JsonSchema\InvalidValue;
use function App\await;
use function App\parallel;
use function App\tpf;

class PlanLatest extends CommonBase
{
    /**
     * initially, ask for all from time 0 to load in all user created data
     *
     * @param int $lastupdate
     * @return array|PromiseInterface
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     * @noinspection SpellCheckingInspection
     */
    public function latest(int $lastupdate): array|PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $qb
            ->select(
                'pl.plan_id as id',
                'pl.plan_country_id as country',
                'pl.plan_name as name',
                'pl.plan_description as description',
                'pl.plan_gametime as startdate',
                'pl.plan_state as state',
                'pl.plan_previousstate as previousstate',
                'pl.plan_lastupdate as lastupdate',
                'pl.plan_lock_user_id as locked',
                'pl.plan_active as active',
                'pl.plan_type as type',
                'pl.plan_energy_error as energy_error',
                'pl.plan_alters_energy_distribution as alters_energy_distribution'
            )
            ->from('plan', 'pl');
        // query general policies of plan, one per column
        $generalPolicyTypes = GeneralPolicyType::getConstants();
        foreach (PolicyTypeName::cases() as $policyTypeName) {
            if (!array_key_exists(strtoupper($policyTypeName->value), $generalPolicyTypes)) {
                continue;
            }
            $po = 'po_'.$policyTypeName->value; // intermediate policy table name
            // this assumes no duplicate policy types per plan
            $qb
                ->leftJoin(
                    'pp',
                    'policy',
                    $po,
                    "pp.policy_id = $po.id and $po.type = ".$qb->createPositionalParameter($policyTypeName->value)
                )
                ->addSelect($po.'.data as ' . $policyTypeName->value.'_data');
        }
        $qb
            ->where('pl.plan_lastupdate >= ' . $qb->createPositionalParameter($lastupdate))
            ->andWhere('pl.plan_active = ' . $qb->createPositionalParameter(1))
            ->leftJoin('pl', 'plan_policy', 'pp', 'pl.plan_id = pp.plan_id')
            ->groupBy('pl.plan_id');
        //get all plans that have changed
        $promise = $this->getAsyncDatabase()->query(
            $qb
        )
        ->then(function (Result $result) {
            $planObj = new Plan();
            $this->asyncDataTransferTo($planObj);
            $plans = ($result->fetchAllRows() ?? []) ?: [];
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
                                'fishing_type',
                                'fishing_amount as effort_weight'
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
                ->then(function (array $results) use (&$plans, $planObj) {
                    /** @var Result[] $results */
                    $toPromiseFunctions = [];
                    foreach ($plans as $pKey => &$d) {
                        $d['layers'] = ($results['layers' . $pKey]->fetchAllRows() ?? []) ?: [];
                        $d['grids'] = collect(($results['grids' . $pKey]->fetchAllRows() ?? []) ?: [])
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

                        $deleted = ($results['deleted' . $pKey]->fetchAllRows() ?? []) ?: [];
                        $d['deleted_grids'] = array();
                        foreach ($deleted as $del) {
                            $d['deleted_grids'][] = $del['grid_persistent'];
                        }

                        $fishingValues = ($results['fishing' . $pKey]->fetchAllRows() ?? []) ?: [];
                        if (count($fishingValues) > 0) {
                            $fishingValues = $planObj->addGearTypeToFishingValues($fishingValues);
                            $d['fishing'] = $fishingValues;
                        }

                        $d['votes'] = ($results['votes' . $pKey]->fetchAllRows() ?? []) ?: [];
                        $d['restriction_settings'] =
                            ($results['restriction_settings' . $pKey]->fetchAllRows() ?? []) ?: [];
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
     * @throws \Swaggest\JsonSchema\Exception
     * @throws InvalidValue
     */
    private function formatByPolicyType(array &$plan, PolicyTypeName $policyType): void
    {
        $policy['policy_type'] = $policyType->value;
        switch ($policyType) {
            case PolicyTypeName::ENERGY_DISTRIBUTION: // PolicyUpdateEnergyPlan
                $policy['alters_energy_distribution'] = $plan['alters_energy_distribution'];
                if (!empty($plan['grids'])) {
                    $policy['grids'] = $plan['grids'];
                }
                if (!empty($plan['deleted_grids'])) {
                    $policy['deleted_grids'] = $plan['deleted_grids'];
                }
                if (!empty($plan['energy_error'])) {
                    $policy['energy_error'] = $plan['energy_error'];
                }
                unset($plan['grids'], $plan['deleted_grids'], $plan['energy_error']);
                break;
            case PolicyTypeName::FISHING_EFFORT: // PolicyUpdateFishingPlan
                if (!empty($plan['fishing'])) {
                    $policy['fishing'] = $plan['fishing'];
                }
                unset($plan['fishing']);
                break;
            case PolicyTypeName::SHIPPING_SAFETY_ZONES: // PolicyUpdateShippingPlan
                if (!empty($plan['restriction_settings'])) {
                    $policy['restriction_settings'] = $plan['restriction_settings'];
                }
                unset($plan['restriction_settings']);
                break;
            default:
                if (!empty($plan[$policyType->value.'_data'])) {
                    $policy = array_merge($policy, json_decode($plan[$policyType->value.'_data'] ?? [], true));
                }
                $policyObj = PolicyDataFactory::createPolicyDataByJsonObject((object)$policy);
                $policy = (array)PolicyDataBase::export($policyObj);
                break;
        }

        $plan['policies'][] = $policy;

        unset(
            $plan[$policyType->value.'_data'],
            // PolicyUpdateEnergyPlan
            $plan['alters_energy_distribution'],
            $plan['grids'],
            $plan['deleted_grids'],
            $plan['energy_error'],
            // PolicyUpdateFishingPlan
            $plan['fishing'],
            // PolicyUpdateShippingPlan
            $plan['restriction_settings']
        );
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
                $generalPolicyTypes = GeneralPolicyType::getConstants();
                foreach (PolicyTypeName::cases() as $policyTypeName) {
                    $policyTypeNameToUpper = strtoupper($policyTypeName->value);
                    if (!array_key_exists($policyTypeNameToUpper, $generalPolicyTypes)) {
                        continue;
                    }
                    $typeValue = $generalPolicyTypes[$policyTypeNameToUpper];
                    if (($type & $typeValue) !== $typeValue) {
                        continue;
                    }
                    $this->formatByPolicyType($plan, $policyTypeName);
                }
                return $plan;
            })
            ->all();
    }

    /**
     * @param float $time
     * @return array|PromiseInterface
     * @throws \Doctrine\DBAL\Exception
     */
    public function getMessages(float $time): array|PromiseInterface
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
