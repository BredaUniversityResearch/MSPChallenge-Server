<?php

namespace App\Domain\API\v1;

use App\Domain\Common\ToPromiseFunction;
use Drift\DBAL\Result;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\resolveOnFutureTick;
use function App\tpf;
use function App\await;

class Plan extends Base
{
    private const ALLOWED = array(
        "Get",
        "All",
        "Post",
        "Message",
        "GetMessages",
        "DeleteLayer",
        "Lock",
        "Unlock",
        "SetState",
        "Name",
        "Description",
        "Date",
        "Layer",
        "Restrictions",
        "ImportRestrictions",
        ["ExportPlansToJson",  Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER],
        "Export",
        "Import",
        "Fishing",
        "GetInitialFishingValues",
        "Type",
        "SetRestrictionAreas",
        "DeleteFishing",
        "DeleteEnergy",
        "SetEnergyError",
        "AddApproval",
        "Vote",
        "DeleteApproval",
        "SetEnergyDistribution"
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup Plan
     * @apiDescription Create a new plan
     * @throws Exception
     * @api {POST} /plan/post Post
     * @apiparam {int} country country id
     * @apiParam {string} name name of the plan
     * @apiParam {int} time when the plan has to be implemented (months since start of project)
     * @apiParam {array} layers json array of layer ids (e.g. [1,4,82])
     * @apiParam {string} type Comma separated string representing the plan type in the format of
     *   "[isEnergy], [isEcology], [isShipping]", e.g. "0, 1, 1".
     * @apiParam {boolean} alters_energy_distribution, in format 0/1, following energy distribution checkbox in Plan
     *   Wizard Step 2b
     * @apiSuccess {int} plan id
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Post(
        int $country,
        string $name = '',
        int $time = -1,
        string $type = '0,0,0',
        array $layers = [],
        bool $alters_energy_distribution = false
    ): int|PromiseInterface {
        $db = $this->getDatabase();
        $id = (int)$db->query(
            "
            INSERT INTO plan (
                plan_country_id, plan_name, plan_gametime, plan_lastupdate, plan_type, plan_alters_energy_distribution
            ) VALUES (?, ?, ?, ?, ?, ?)
            ",
            array($country, $name, $time, microtime(true), $type, $alters_energy_distribution),
            true
        );
        foreach ($layers as $layer) {
            if (is_numeric($layer)) {
                $lid = $db->query(
                    "INSERT INTO layer(layer_original_id) VALUES (?)",
                    array($layer),
                    true
                );

                $db->query(
                    "INSERT INTO plan_layer (plan_layer_plan_id, plan_layer_layer_id) VALUES (?, ?)",
                    array($id, $lid)
                );
            }
        }
        $this->UpdatePlanConstructionTime($id);

        // @todo: fake it till you make it... but fix it later!
        if ($this->isAsync()) {
            return resolveOnFutureTick(new Deferred(), (int)$id)->promise();
        }
        return (int)$id;
    }

    /**
     * @apiGroup Plan
     * @apiDescription Get a specific plan
     * @throws Exception
     * @api {POST} /post/get Get
     * @apiParam {int} id of plan to return
     * @apiSuccess {string} JSON object containing all plan metadata + comments
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Get(int $id): array
    {
        $data = $this->getDatabase()->query("SELECT * FROM plan WHERE plan_id=?", array($id));
        $data[0]["layers"] = $this->getDatabase()->query(
            "SELECT plan_layer_layer_id FROM plan_layer WHERE plan_layer_plan_id=?",
            array($id)
        );
        $data[0]["comments"] = $this->getDatabase()->query(
            "SELECT * from plan_message WHERE plan_message_plan_id=?",
            array($id)
        );
        return $data;
    }

    /**
     * @apiGroup Plan
     * @apiDescription Get all plans
     * @throws Exception
     * @api {POST} /plan/all All
     * @apiSuccess {string} JSON object of all plan metadata + comments
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function All(): array
    {
        /** @var array{plan_id: int} $data */
        $data = $this->getDatabase()->query("SELECT * FROM plan WHERE plan_active=?", array(1));

        self::Debug($data);

        foreach ($data as &$d) {
            $d["layers"] = $this->getDatabase()->query(
                "SELECT plan_layer_layer_id FROM plan_layer WHERE plan_layer_plan_id=?",
                array($d["plan_id"])
            );
            $data["comments"] = $this->getDatabase()->query(
                "SELECT * FROM plan_message WHERE plan_message_plan_id=?",
                array($d['plan_id'])
            );
        }

        return $data;
    }

    /**
     * @apiGroup Plan
     * @apiDescription Delete a plan
     * @throws Exception
     * @api {POST} /plan/delete Delete
     * @apiParam {int} id of the plan to delete
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Delete(int $id): void
    {
        $this->getDatabase()->query(
            "UPDATE plan SET plan_active=?, plan_lastupdate=? WHERE plan_id=?",
            array(0, microtime(true), $id)
        );
    }

    /**
     * @apiGroup Plan
     * @throws Exception
     * @api {POST} /plan/DeleteEnergy Delete Energy
     * @apiParam {int} plan plan id
     * @apiDescription delete all grids & associated grid data based on a plan id
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteEnergy(int $plan): void
    {
        // Put an energy error in depent plans, similar to "api/plan/SetEnergyError" with "check_dependent_plans" set to
        //   1.
        // This should ofc be done before energy elements are removed from the plan.
        $planData = $this->getDatabase()->query("SELECT plan_name FROM plan WHERE plan_id = ?", array($plan));
        await($this->setAllDependentEnergyPlansToError($plan, $planData[0]["plan_name"]));

        $this->getDatabase()->query("DELETE FROM grid WHERE grid_plan_id=?", array($plan));
        // Set the target plans energy error to 0
        $this->getDatabase()->query(
            "UPDATE plan SET plan_lastupdate = ?, plan_energy_error = 0 WHERE plan_id = ?",
            array(microtime(true), $plan)
        );
    }

    /**
     * @apiGroup Plan
     * @apiDescription Add a new layer to a plan
     * @throws Exception
     * @api {POST} /plan/layer Layer
     * @apiParam {int} id id of the plan
     * @apiParam {int} layerid id of the original layer
     * @noinspection SpellCheckingInspection
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Layer(int $id, int $layerid): int|PromiseInterface
    {
        $db = $this->getDatabase();
        $lid = $db->query(
            "INSERT INTO layer (layer_original_id) VALUES (?)",
            array($layerid),
            true
        );

        $rid = $db->query(
            "INSERT INTO plan_layer (plan_layer_plan_id, plan_layer_layer_id) VALUES (?, ?)",
            array($id, $lid),
            true
        );

        $this->UpdatePlanConstructionTime($id);

        // @todo: fake it till you make it... but fix it later!
        if ($this->isAsync()) {
            return resolveOnFutureTick(new Deferred(), (int)$rid)->promise();
        }
        return (int)$rid;
    }

    /**
     * Updates the plan_constructiontime field in the plan database.
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function UpdatePlanConstructionTime(int $planId): void
    {
        $highest = 0;

        $db = $this->getDatabase();
        $planlayers = $db->query("SELECT l2.layer_states FROM plan_layer
				LEFT JOIN layer l1 ON l1.layer_id=plan_layer.plan_layer_layer_id
				LEFT JOIN layer l2 ON l1.layer_original_id=l2.layer_id
				WHERE plan_layer_plan_id=?", array($planId));

        foreach ($planlayers as $pl) {
            $json = json_decode($pl['layer_states'], true);

            foreach ($json as $j) {
                if ($j["state"] == "ASSEMBLY" && $j['time'] > $highest) {
                    $highest = $j['time'];
                    break;
                }
            }
        }

        $db->query(
            "UPDATE plan SET plan_lastupdate=?, plan_constructionstart=plan_gametime-? WHERE plan_id=?",
            array(microtime(true), $highest, $planId)
        );
    }

    /**
     * @apiGroup Plan
     * @apiDescription Delete a layer from a plan
     * @throws Exception
     * @api {POST} /plan/DeleteLayer Delete Layer
     * @apiParam {int} id id of the layer to remove
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteLayer(int $id): void
    {
        $db = $this->getDatabase();
        $planid = $db->query(
            "SELECT plan_layer_plan_id as id FROM plan_layer WHERE plan_layer_layer_id=?",
            array($id)
        );

        //Try to nuke the energy data on all layers.
        $energy = new Energy();
        $energy->DeleteEnergyInformationFromLayer($id);

        $db->query("DELETE FROM geometry WHERE geometry_layer_id=?", array($id));
        $db->query("DELETE FROM plan_layer WHERE plan_layer_layer_id=?", array($id));
        $db->query(
            "DELETE FROM plan_delete WHERE plan_delete_geometry_persistent IN (
					SELECT geometry_persistent FROM geometry
					LEFT JOIN layer ON geometry_layer_id=layer_original_id
					WHERE layer_id=?
				)",
            array($id)
        );

        //Invalidate all warnings from this plan layer
        $warning = new Warning();
        $warning->RemoveAllWarningsForLayer($planid[0]['id'], true); // hard-delete

        // @todo: also delete everything to do with energy connected to this

        $db->query(
            "UPDATE plan SET plan_lastupdate=? WHERE plan_id=?",
            array(microtime(true), $planid[0]['id'])
        );
    }

    /**
     * @apiGroup Plan
     * @apiDescription Set the state of a plan
     * @throws Exception
     * @api {POST} /plan/SetState Set State
     * @apiParam {int} id id of the plan
     * @apiParam {string} state state to set the plan to (DESIGN, CONSULTATION, APPROVAL, APPROVED, DELETED)
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetState(int $id, string $state, int $user): void
    {
        $currentPlanData = $this->getDatabase()->query(
            "SELECT plan_state, plan_name, plan_type FROM plan WHERE plan_id = ? AND plan_lock_user_id = ?",
            array($id, $user)
        );
        if (count($currentPlanData) == 0) {
            throw new Exception("Trying to set plan state of plan ".$id." without user ".$user." having it locked");
        }

        $previousState = $currentPlanData[0]['plan_state'];
        $isEnergyPlan = explode(",", $currentPlanData[0]['plan_type'])[0] == "1";

        $performEnergyDependencyCheck = false;  //Design / Deleted -> Concultation / Approval / Approved
        $performEnergyOverlapCheck = false;     //Concultation / Approval / Approved -> Design / Deleted

        if ($isEnergyPlan) {
            if ($state == "DESIGN" || $state == "DELETED") {
                if ($previousState == "CONSULTATION" || $previousState == "APPROVAL" || $previousState == "APPROVED") {
                    $performEnergyDependencyCheck = true;
                }
            } else {
                if ($previousState == "DESIGN" || $previousState == "DELETED") {
                    $performEnergyOverlapCheck = true;
                }
            }
        }

        // We explicitly don't set the plan_updatetime here to prevent issues with half-updates. plan_updatettime is set
        //   when the plan is unlocked again.
        $this->getDatabase()->query(
            "UPDATE plan SET plan_previousstate=plan_state, plan_state=? WHERE plan_id=?",
            array($state, $id)
        );
        if ($previousState == "APPROVAL") {
            $this->getDatabase()->query(
                "UPDATE approval SET approval_vote = -1 WHERE approval_plan_id = ?",
                array($id)
            );
        }

        if ($isEnergyPlan) {
            //Set dependent plans back to design and set the energy error.
            $erroringEnergyPlans = array();
            $energy = new Energy();
            if ($performEnergyDependencyCheck) {
                await($energy->findDependentEnergyPlans($id, $erroringEnergyPlans));
            }
            if ($performEnergyOverlapCheck) {
                $energy->FindOverlappingEnergyPlans($id, $erroringEnergyPlans);
            }

            foreach ($erroringEnergyPlans as $planId) {
                $this->getDatabase()->query(
                    "
                    UPDATE plan
                    SET plan_previousstate = plan_state, plan_state = ?, plan_lastupdate = ?, plan_energy_error = 1
                    WHERE plan_id = ? AND plan_state <> 'DELETED'
                    ",
                    array("DESIGN", microtime(true), $planId)
                );
                $this->Message(
                    $planId,
                    1,
                    "SYSTEM",
                    "Plan was moved back to design. An energy conflict was found when plan \"".
                    $currentPlanData[0]["plan_name"]."\" was moved to a different state."
                );
            }
        }

        //$this->DBCommitTransaction();
    }

    /**
     * update the layer states, done automatically using game ticks
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function updateLayerState(int $currentGameTime): PromiseInterface
    {
        return $this->updateAllPlanLayerStates($currentGameTime)
            ->then(function (/*Result $result*/) use ($currentGameTime) {
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $this->getAsyncDatabase()->query(
                    $qb
                        ->select(
                            'plan_id',
                            'plan_name',
                            'plan_gametime',
                            'plan_constructionstart',
                            'plan_state'
                        )
                        ->from('plan')
                        ->where('plan_constructionstart <= ' . $qb->createPositionalParameter($currentGameTime))
                        ->andWhere('plan_state <> ' . $qb->createPositionalParameter('IMPLEMENTED'))
                        ->andWhere('plan_state <> ' . $qb->createPositionalParameter('DELETED'))
                );
            })
            ->then(function (Result $result) use ($currentGameTime) {
                $plansToUpdate = $result->fetchAllRows();
                $toPromiseFunctions = [];
                foreach ($plansToUpdate as $plan) {
                    $toPromiseFunctions[] = tpf(function () use ($currentGameTime, $plan) {
                        return $this->updatePlanState($currentGameTime, $plan);
                    });
                }
                return parallel($toPromiseFunctions);
            });
    }

    /**
     * @throws Exception
     */
    private function archivePlan(int $planId, string $planName, string $message): PromiseInterface
    {
        $toPromiseFunctions[] = tpf(function () use ($planId, $message) {
            return $this->messageAsync($planId, 1, 'SYSTEM', $message);
        });
        // set all plans to deleted when it has not been approved or implemented yet and the start construction date has
        //   already passed
        $toPromiseFunctions[] = tpf(function () use ($planId) {
            return $this->getAsyncDatabase()->update(
                'plan',
                [
                    'plan_id' => $planId
                ],
                [
                    'plan_lastupdate' => microtime(true),
                    'plan_state' => 'DELETED'
                ]
            );
        });
        $toPromiseFunctions[] = tpf(function () use ($planId, $planName) {
            return $this->setAllDependentEnergyPlansToError($planId, $planName);
        });
        return parallel($toPromiseFunctions);
    }

    /**
     * @throws Exception
     */
    private function setAllDependentEnergyPlansToError(int $planId, string $planName): PromiseInterface
    {
        $deferred = new Deferred();
        $dependentPlans = [];
        $energy = new Energy();
        $this->asyncDataTransferTo($energy);
        $energy->findDependentEnergyPlans($planId, $dependentPlans)
            ->then(function () use ($planName, &$dependentPlans) {
                $toPromiseFunctions = [];
                foreach ($dependentPlans as $erroredPlanId) {
                    $toPromiseFunctions[$erroredPlanId] = tpf(function () use ($erroredPlanId, $planName) {
                        $qb = $this->getAsyncDatabase()->createQueryBuilder();
                        return $this->getAsyncDatabase()->query(
                            $qb
                                ->update('plan', 'p')
                                ->set('p.plan_energy_error', '1')
                                ->set('p.plan_previousstate', 'p.plan_state')
                                ->set('p.plan_state', $qb->createPositionalParameter('DESIGN'))
                                ->set('p.plan_lastupdate', $qb->createPositionalParameter(microtime(true)))
                                ->where('p.plan_id = ' . $qb->createPositionalParameter($erroredPlanId))
                        )
                        ->then(function (/*Result $result*/) use ($planName, $erroredPlanId) {
                            return $this->messageAsync(
                                $erroredPlanId,
                                1,
                                "SYSTEM",
                                "Plan was moved back to design after plan \"".$planName.
                                "\" was archived due to conflicts in the energy system."
                            );
                        });
                    });
                }
                // might cause db locks if the energy grids are shared between plans
                //  let db handle it and see
                return parallel($toPromiseFunctions);
            })
            ->done(
                function (/* array $results */) use ($deferred) {
                    $deferred->resolve(); // return void, we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        return $deferred->promise();
    }

    /**
     * @throws Exception
     */
    private function updatePlanState(int $currentGametime, array $planObject): PromiseInterface
    {
        if ($currentGametime == $planObject['plan_constructionstart']) {
            if ($planObject['plan_state'] != "APPROVED") {
                return $this->archivePlan(
                    $planObject["plan_id"],
                    $planObject["plan_name"],
                    "Construction start time was reached, but plan was not approved. Plan has been archived."
                );
            } elseif ($this->PlanHasErrors($planObject['plan_id'])) {
                return $this->archivePlan(
                    $planObject["plan_id"],
                    $planObject["plan_name"],
                    "Plan had errors upon reaching the construction start date. Plan has been archived."
                );
            }
        }
        if ($currentGametime < $planObject['plan_gametime']) {
            return resolveOnFutureTick(new Deferred(), [])->promise();
        }
        if ($planObject['plan_state'] != "APPROVED") {
            return $this->archivePlan(
                $planObject["plan_id"],
                $planObject["plan_name"],
                "Implementation time was reached, but plan was not approved. Plan has been archived."
            );
        }

        if ($this->PlanHasErrors($planObject['plan_id'])) {
            return $this->archivePlan(
                $planObject["plan_id"],
                $planObject["plan_name"],
                "Plan had errors upon reaching the implementation date. Plan has been archived."
            );
        }
        
        //plan is implemented, set plan to IMPLEMENTED and handle energy grid
        return $this->getAsyncDatabase()->update(
            'plan',
            [
                'plan_id' => $planObject['plan_id']
            ],
            [
                'plan_lastupdate' => microtime(true),
                'plan_state' => 'IMPLEMENTED'
            ]
        )
        ->then(function (/*Result $result*/) use ($planObject) {
            $toPromiseFunctions[] = tpf(function () use ($planObject) {
                // Disable all geometry that we reference in previous plans.
                return $this->disableReferencedGeometryFromPreviousPlans($planObject['plan_id']);
            });
            $toPromiseFunctions[] = tpf(function () use ($planObject) {
                return $this->updateFishing($planObject['plan_id']);
            });
            return parallel($toPromiseFunctions);
        })
        ->then(function (/*array $results*/) use ($planObject) {
            return $this->messageAsync($planObject['plan_id'], 1, "SYSTEM", "Plan was implemented.");
        })
        ->then(function (/*Result $result*/) use ($planObject) {
            // Update energy grid states, disable all grids that have been deleted and have been reimplemented
            //   in this plan.
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            /** @noinspection SqlResolve */
            return $this->getAsyncDatabase()->query(
                $qb
                    ->update('grid')
                    ->set('grid_active', '0')
                    ->where($qb->expr()->or(
                        $qb->expr()->in(
                            'grid_persistent',
                            '
                            SELECT grid_removed_grid_persistent
                            FROM grid_removed
                            WHERE grid_removed_plan_id = ' . $qb->createPositionalParameter($planObject['plan_id'])
                        ),
                        $qb->expr()->in(
                            'grid_persistent',
                            // !!! important !!! we are doing a (SELECT * FROM grid) WHY ?
                            //   To prevent error: "You can't specify target table '...' for update in FROM clause"
                            //   The MySQL query optimizer does a derived merge optimization for the first query
                            //   (which causes it to fail with the error), but the second query doesn't qualify
                            //   for the derived merge optimization.
                            //   Hence, the optimizer is forced to execute the sub query first.
                            'SELECT g.grid_persistent FROM (SELECT * FROM grid) g WHERE g.grid_plan_id = ' .
                                $qb->createPositionalParameter($planObject['plan_id'])
                        )
                    ))

                    // since it is not possible to use this innerJoin with an update with DBAL,
                    //  use a sub query instead:
                    //  ->innerJoin('grid', 'plan', 'plan', 'grid.grid_plan_id = plan.plan_id')
                    ->andWhere(
                        $qb->expr()->in(
                            'grid.grid_plan_id',
                            '
                            SELECT plan_id
                            FROM plan p
                            WHERE p.plan_id = grid.grid_plan_id AND p.plan_gametime < ' .
                                $qb->createPositionalParameter($planObject['plan_gametime'])
                        )
                    )
            )
            ->then(function (Result $result) {
                return $result->fetchAllRows();
            });
        });
    }

    /**
     * @throws Exception
     */
    private function updatePlanLayerState(array $planLayer, int $currentGameTime): ?ToPromiseFunction
    {
        if ($planLayer['plan_layer_state'] == "INACTIVE") {
            return null;
        }

        $json = json_decode($planLayer['layer_states'], true);
        $planStartTime = $planLayer['plan_gametime'];
        $state = $planLayer['plan_layer_state'];

        // executive decision, we're out of time and this still hasn't been implemented client side and has no
        //   config done for it. I'm defaulting everything to only use the assembly time and leave it active
        //   forever. This code was implemented in the early months and has is a liability now.

        if ($state == 'WAIT') {
            $assemblyTime = $json[0]['time'] ?? 0;
            if ($currentGameTime >= $planStartTime) {
                $state = 'ACTIVE';
            } elseif ($currentGameTime >= $planStartTime - $assemblyTime) {
                $state = 'ASSEMBLY';
            }
        } else {
            if ($currentGameTime >= $planStartTime) {
                $state = 'ACTIVE';
            }
        }

        //self::Debug("setting state of layer to " . $state);
        return tpf(function () use ($planLayer, $state) {
            return $this->getAsyncDatabase()->update(
                'plan_layer',
                [
                    'plan_layer_id' => $planLayer['plan_layer_id']
                ],
                [
                    'plan_layer_state' => $state
                ]
            )
            ->then(function (/*Result $result*/) use ($state, $planLayer) {
                switch ($state) {
                    case 'ASSEMBLY':
                        //if the state of the layer is set to assembly, notify MEL that the assembly has started
                        return $this->getAsyncDatabase()->update(
                            'layer',
                            [
                                'layer_id' => $planLayer['oldid']
                            ],
                            [
                                'layer_melupdate' => 1
                            ]
                        );
                    case 'ACTIVE':
                        $qb = $this->getAsyncDatabase()->createQueryBuilder();
                        return $this->getAsyncDatabase()->query(
                            $qb
                                ->update('geometry', 'g')
                                ->set('g.geometry_active', '0')

                                // since it is not possible to use this innerJoin with an update with DBAL,
                                //   use a sub query instead:
                                // ->innerJoin(
                                //     'g',
                                //     'plan_delete',
                                //     'p',
                                //     $qb->expr()->and(
                                //         $qb->expr()->or(
                                //             'g.geometry_persistent=p.plan_delete_geometry_persistent',
                                //             'g.geometry_subtractive=p.plan_delete_geometry_persistent'
                                //         ),
                                //         'p.plan_delete_plan_id=' .
                                //         $qb->createPositionalParameter($planLayer['plan_id'])
                                //     )
                                //)

                                // !!! important !!! we are doing a (SELECT * FROM geometry) WHY ?
                                //   To prevent error: "You can't specify target table '...' for update in FROM clause"
                                //   The MySQL query optimizer does a derived merge optimization for the first query
                                //   (which causes it to fail with the error), but the second query doesn't qualify
                                //   for the derived merge optimization.
                                //   Hence, the optimizer is forced to execute the sub query first.
                                ->where(
                                    $qb->expr()->in(
                                        'g.geometry_id',
                                        '
                                        SELECT geometry_id
                                        FROM (SELECT * FROM geometry) g2
                                        INNER JOIN plan_delete p ON (
                                            (
                                                g2.geometry_persistent=p.plan_delete_geometry_persistent OR
                                                g2.geometry_subtractive=p.plan_delete_geometry_persistent
                                            ) AND
                                            p.plan_delete_plan_id= ?
                                        )
                                        '
                                    )
                                )
                                ->setParameters([$planLayer['plan_id']])
                        )
                        ->then(function (/*Result $result*/) use ($planLayer) {
                            // we don't have to do anything here except make sure the parent layer is set to be
                            //   updated in MEL, the merging of geometry is done while getting the layer data in
                            //   Layer->GeometryExportName()
                            return $this->getAsyncDatabase()->update(
                                'layer',
                                [
                                    'layer_id' => $planLayer['oldid']
                                ],
                                [
                                    'layer_melupdate' => 1
                                ]
                            );
                        });
                    default:
                        return null;
                }
            });
        });
    }

    /**
     * @throws Exception
     */
    private function updateAllPlanLayerStates(int $currentGameTime): PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
            $qb
                ->select(
                    'pl.plan_layer_id',
                    'oldlayer.layer_states',
                    'p.plan_gametime',
                    'pl.plan_layer_state',
                    'p.plan_id',
                    'oldlayer.layer_id as oldid',
                    'newlayer.layer_id as newid'
                )
                ->from('plan', 'p')
                ->leftJoin('p', 'plan_layer', 'pl', 'p.plan_id=pl.plan_layer_plan_id')
                ->leftJoin('pl', 'layer', 'newlayer', 'pl.plan_layer_layer_id=newlayer.layer_id')
                ->leftJoin('newlayer', 'layer', 'oldlayer', 'newlayer.layer_original_id=oldlayer.layer_id')
                ->where('p.plan_state = ' . $qb->createPositionalParameter('APPROVED'))
                ->andWhere('p.plan_constructionstart <= ' . $qb->createPositionalParameter($currentGameTime))
        )
        ->then(function (Result $result) use ($currentGameTime) {
            $planLayers = $result->fetchAllRows();
            $toPromiseFunctions = [];
            foreach ($planLayers as $planLayer) {
                if (null === $toPromiseFunction = $this->updatePlanLayerState($planLayer, $currentGameTime)) {
                    continue;
                }
                $toPromiseFunctions[] = $toPromiseFunction;
            }
            return parallel($toPromiseFunctions);
        });
    }

    /**
     * Returns true if there are errors in the current plan, false if no errors or only warnings.
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function PlanHasErrors(int $currentPlanId): bool
    {
        $energyError = $this->getDatabase()->query(
            "SELECT plan_energy_error FROM plan WHERE plan.plan_id = ?",
            array($currentPlanId)
        );

        $errors = $this->getDatabase()->query(
            "
            SELECT COUNT(warning_id) as error_count
            FROM warning
            INNER JOIN plan_layer ON warning.warning_layer_id = plan_layer.plan_layer_layer_id
            INNER JOIN plan ON plan_layer.plan_layer_plan_id = plan.plan_id
            WHERE plan_layer.plan_layer_plan_id = ? AND warning_active = 1 AND warning_issue_type = 'ERROR'
            ",
            array($currentPlanId)
        );
        return ((int)$errors[0]["error_count"]) > 0 || $energyError[0]["plan_energy_error"] == 1;
    }

    /**
     * @throws Exception
     */
    private function disableReferencedGeometryFromPreviousPlans(int $currentPlanId): PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
            $qb
                ->select('g.geometry_id')
                ->from('geometry', 'g')
                ->leftJoin('g', 'plan_layer', 'pl', 'g.geometry_layer_id = pl.plan_layer_layer_id')
                ->leftJoin('pl', 'plan', 'p', 'pl.plan_layer_plan_id = p.plan_id')
                ->where(
                    $qb->expr()->and(
                        $qb->expr()->or(
                            'p.plan_id != ' . $qb->createPositionalParameter($currentPlanId),
                            'p.plan_id IS NULL'
                        ),
                        $qb->expr()->or(
                            'p.plan_state = ' . $qb->createPositionalParameter('IMPLEMENTED'),
                            'p.plan_state IS NULL'
                        ),
                        $qb->expr()->in(
                            'g.geometry_persistent',
                            '
                            SELECT geometry.geometry_persistent FROM plan
                            INNER JOIN plan_layer ON plan_layer.plan_layer_plan_id = plan.plan_id
                            INNER JOIN geometry ON plan_layer.plan_layer_layer_id = geometry.geometry_layer_id
                            WHERE plan.plan_id = ' . $qb->createPositionalParameter($currentPlanId)
                        )
                    )
                )
        )
        ->then(function (Result $result) {
            $idsToDisable = collect($result->fetchAllRows() ?? [])->flatten()->all();
            if (empty($idsToDisable)) {
                return new Result([], null, 0);
            }
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->update('geometry')
                    ->set('geometry_active', '0')
                    ->where(
                        'geometry_id IN (' .
                            implode(',', $idsToDisable) .
                        ') or geometry_subtractive IN (' . implode(',', $idsToDisable) . ')'
                    )
            );
        });
    }

    /**
     * @throws Exception
     */
    private function updateFishing(int $planId): PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
            $qb
                ->select('*')
                ->from('fishing')
                ->where('fishing_plan_id = ' . $qb->createPositionalParameter($planId))
        )
        ->then(function (Result $result) use ($planId) {
            $fishing = $result->fetchAllRows();
            $toPromiseFunctions = [];
            foreach ($fishing as $fish) {
                // skip setting the fish record to inactive for this plan
                if ($fish['fishing_plan_id'] == $planId) {
                    continue;
                }
                $toPromiseFunctions[] = tpf(function () use ($fish) {
                    return $this->getAsyncDatabase()->update(
                        'fishing',
                        [
                            'fishing_type' => $fish['fishing_type'],
                            'fishing_country_id' => $fish['fishing_country_id']
                        ],
                        [
                            'fishing_active' => 0
                        ]
                    );
                });
            }
            $toPromiseFunctions[] = tpf(function () use ($planId) {
                return $this->getAsyncDatabase()->update(
                    'fishing',
                    [
                        'fishing_plan_id' => $planId
                    ],
                    [
                        'fishing_active' => 1
                    ]
                );
            });
            return parallel($toPromiseFunctions);
        });
    }

    /**
     * @apiGroup Plan
     * @apiDescription Add a new list of countries that require approval for a plan
     * @throws Exception
     * @api {POST} /plan/AddApproval Add Approval
     * @apiParam {int} id id of the plan
     * @apiParam {array} countries json array of country ids
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function AddApproval(int $id, array $countries = []): ?PromiseInterface
    {
        $deferred = new Deferred();
        $plan = new Plan();
        $this->asyncDataTransferTo($plan);
        $plan->setAsync(true);
        $plan->DeleteApproval($id)
            ->then(function (/* Result $result */) use ($id, $countries) {
                $toPromiseFunctions = [];
                foreach ($countries as $country) {
                    $toPromiseFunctions[] = tpf(function () use ($id, $country) {
                        return $this->getAsyncDatabase()->insert('approval', [
                            'approval_plan_id' => $id,
                            'approval_country_id' => $country
                        ]);
                    });
                }
                return parallel($toPromiseFunctions);
            })
            ->done(
                /** @var Result[] $results */
                function (/* array $results */) use ($deferred) {
                    $deferred->resolve(); // return void, we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Plan
     * @apiDescription Add a new list of countries that require approval for a plan
     * @throws Exception
     * @api {POST} /plan/Vote Vote
     * @apiParam {int} country country id
     * @apiParam {int} plan plan id
     * @apiParam {int} vote (-1 = undecided/abstain, 0 = no, 1 = yes)
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Vote(int $country, int $plan, int $vote): void
    {
        $this->getDatabase()->query(
            "UPDATE approval SET approval_vote=? WHERE approval_country_id=? AND approval_plan_id=?",
            array($vote, $country, $plan)
        );
        $this->getDatabase()->query(
            "UPDATE plan SET plan_lastupdate=? WHERE plan_id=?",
            array(microtime(true), $plan)
        );
    }

    /**
     * @apiGroup Plan
     * @apiDescription Delete all required approvals for a plan, either when it's not necessary anymore or when you
     *   need to submit a new list
     * @throws Exception
     * @api {POST} /plan/DeleteApproval Delete Approval
     * @apiParam {int} id id of the plan
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteApproval(int $id): ?PromiseInterface
    {
        $deferred = new Deferred();
        $this->getAsyncDatabase()->delete('approval', ['approval_plan_id' => $id])
            ->done(
                function (/* Result $result */) use ($deferred) {
                    $deferred->resolve(); // return void, we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetBaseGeometryInformation(
        int $geometryId,
        array $remappedGeometryIds,
        array $remappedPersistentGeometryIds
    ): array {
        if (array_key_exists($geometryId, $remappedGeometryIds)) {
            $geometryId = $remappedGeometryIds[$geometryId];
        }

        $baseInfo["geometry_id"] = $geometryId;

        $baseInfoQuery = $this->getDatabase()->query(
            "SELECT geometry_id, geometry_persistent, geometry_mspid FROM geometry WHERE geometry_id = ?",
            array($geometryId)
        );
        $persistentId = $baseInfoQuery[0]["geometry_persistent"];
        $mspIdQuery = $this->getDatabase()->query(
            "SELECT geometry_mspid FROM geometry WHERE geometry_persistent = ? AND geometry_mspid IS NOT NULL",
            array($persistentId)
        );
        if (count($mspIdQuery) > 0) {
            $baseInfo["geometry_mspid"] = $mspIdQuery[0]["geometry_mspid"];
        }

        if (array_key_exists($persistentId, $remappedPersistentGeometryIds)) {
            $baseInfo["geometry_persistent"] = $remappedPersistentGeometryIds[$persistentId];
        } else {
            $baseInfo["geometry_persistent"] = $persistentId;
        }

        return $baseInfo;
    }

    /**
     * @apiGroup Plan
     * @apiDescription Returns a json-encoded object which represents the exported plan data for the current game
     *   session. Returns an empty string on failure.
     * @throws Exception
     * @api {POST} /plan/DeleteLayer Delete Layer
     * @apiSuccess {string} JSON encoded object with fields "success" (0|1) Successful operation?, "message"
     *   (string) Error messages that might have occured, "data" (object) Exported object that represents the exported
     *   plan data.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ExportPlansToJson(int $session = 0): array
    {
        if ($session > 0) {
            $this->setGameSessionId($session);
        }
        $dataToReturn = [];
        $errors = [];
        if (!$this->Export($dataToReturn, $errors)) {
            throw new Exception(var_export($errors, true));
        }
        return $dataToReturn;
    }

    /**
     * export the plans for the config file
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Export(array &$result, ?array &$errors = null): bool
    {
        //Make sure we don't export plans with NULL name as these are auto generated fishing plans.
        /** @var array<array{plan_id: int, grids: array<array{grid_id: int, energy: array, removed: array, sockets: array, sources: array}>, layers: array<array{layer_id: int, layer_editing_type: string, warnings: array, deleted: array<array{geometry_id: int, base_geometry_info: array}>, geometry: array<array{geometry_id: int, energy_output: array, data: ?string, cable: array{start: array, end: array}}>}>}> $plans */
        $plans = $this->getDatabase()->query(
            "
            SELECT plan_id, plan_country_id, plan_name, plan_gametime, plan_type, plan_alters_energy_distribution
            FROM plan WHERE plan_active=? AND plan_state<>? AND plan_name <> ''
            ",
            array(1, "DELETED")
        );

        //Key value pair of persistent IDs have been remapped (key) to the new value (value).
        $remappedPersistentGeometryIds = array();
        //Key value pair of geometry ids that have been flattened (removed) to a new value.
        $remappedGeometryIds = array();

        foreach ($plans as &$d) {
            $d["layers"] = $this->getDatabase()->query(
                "
                SELECT plan_layer_layer_id as layer_id, l2.layer_name as name, l2.layer_editing_type FROM plan_layer
				LEFT JOIN layer l1 ON plan_layer_layer_id=l1.layer_id
				LEFT JOIN layer l2 ON l1.layer_original_id=l2.layer_id
				WHERE plan_layer_plan_id=?
				",
                array($d["plan_id"])
            );
            // notice the "1 as active" in following line - all grids exported should be registered as active, they are
            //   put to inactive during simulation, when a new plan that includes a grid update is implemented
            $d['grids'] = $this->getDatabase()->query(
                "SELECT grid_id, grid_name as name, 1 as active, grid_persistent FROM grid WHERE grid_plan_id=?",
                array($d['plan_id'])
            );
        }

        foreach ($plans as &$d) {
            $d['fishing'] = $this->getDatabase()->query(
                "SELECT * FROM fishing WHERE fishing_plan_id=?",
                array($d['plan_id'])
            );

            $d['messages'] = $this->getDatabase()->query(
                "
                SELECT
                plan_message_country_id as country_id,
                plan_message_user_name as user_name,
                plan_message_text as text,
                plan_message_time as time
                FROM plan_message
                WHERE plan_message_plan_id = ?
                ",
                array($d['plan_id'])
            );

            $d['restriction_settings'] = $this->ExportRestrictionSettingsForPlan($d['plan_id']);

            foreach ($d['layers'] as &$l) {
                $l['geometry'] = $this->ExportGeometryForLayer(
                    $l['layer_id'],
                    $remappedGeometryIds,
                    $remappedPersistentGeometryIds
                );
            }

            foreach ($d['layers'] as &$l) {
                $l['warnings'] = $this->ExportWarningsForLayer($l['layer_id']);

                $l['deleted'] = $this->getDatabase()->query(
                    "
                    SELECT geometry_id
                    FROM plan_delete
                    LEFT JOIN geometry ON geometry.geometry_id=plan_delete.plan_delete_geometry_persistent
                    WHERE plan_delete_layer_id=?
                    ",
                    array($l['layer_id'])
                );
                foreach ($l['deleted'] as &$geom) {
                    $geom['base_geometry_info'] = $this->GetBaseGeometryInformation(
                        $geom['geometry_id'],
                        $remappedGeometryIds,
                        $remappedPersistentGeometryIds
                    );
                }

                foreach ($l['geometry'] as &$geom) {
                    $geom['data'] = json_decode($geom['data'], true);
                    if (empty($geom['data'])) {
                        $geom['data'] = null; // MSP-2942 & MSP-2972
                    }

                    //get the cable data for this geometry, if it exists
                    $cableData = $this->getDatabase()->query(
                        "
                        SELECT
							energy_connection_start_id as start,
							energy_connection_end_id as end,
							energy_connection_start_coordinates as coordinates
						FROM energy_connection WHERE energy_connection_cable_id=? AND energy_connection_active=1
						",
                        array($geom['geometry_id'])
                    );

                    if (!empty($cableData)) {
                        $geom['cable'] = $cableData[0];

                        $geom['cable']['start'] = $this->GetBaseGeometryInformation(
                            $geom['cable']['start'],
                            $remappedGeometryIds,
                            $remappedPersistentGeometryIds
                        );
                        $geom['cable']['end'] = $this->GetBaseGeometryInformation(
                            $geom['cable']['end'],
                            $remappedGeometryIds,
                            $remappedPersistentGeometryIds
                        );
                        //Sanity check that we don't export cables without connections.
                    } elseif ($l['layer_editing_type'] == "cable") {
                        $errors[] = "Got geometry ID ".$geom['geometry_id'].
                            " which is on a cable layer, but has no active cable connections";
                    }

                    $energyOutput = $this->getDatabase()->query(
                        "
                        SELECT
							energy_output_maxcapacity as maxcapacity,
							energy_output_active as active
						FROM energy_output WHERE energy_output_geometry_id = ?
						",
                        array($geom['geometry_id'])
                    );
                    if (!empty($energyOutput)) {
                        $geom['energy_output'] = $energyOutput;
                    } elseif (in_array($l['layer_editing_type'], array(
                        "cable","transformer","socket","sourcepoint","sourcepolygon","sourcepolygonpoint"
                    ))) { //Sanity check that energy types have the required values.
                        $errors[] = "Got geometry ID ".$geom['geometry_id'].
                            " which is on an energy type layer (Layer ID: ".$l['layer_id']." type: ".
                            $l['layer_editing_type'].") but does not have energy output associated with it.";
                    }
                }
            }

            foreach ($d['grids'] as &$grid) {
                $grid['energy'] = $this->getDatabase()->query(
                    "
                    SELECT grid_energy_country_id as country, grid_energy_expected as expected
                    FROM grid_energy WHERE grid_energy_grid_id = ?
                    ",
                    array($grid['grid_id'])
                );

                $grid['removed'] = $this->getDatabase()->query(
                    "
                    SELECT grid_removed_grid_persistent as grid_persistent
                    FROM grid_removed
                    WHERE grid_removed_plan_id = ?
                    ",
                    array($grid['grid_id'])
                );

                $sockets = $this->getDatabase()->query(
                    "SELECT grid_socket_geometry_id as geometry_id FROM grid_socket WHERE grid_socket_grid_id = ?",
                    array($grid['grid_id'])
                );
                foreach ($sockets as $socket) {
                    $socketData["geometry"] = $this->GetBaseGeometryInformation(
                        $socket["geometry_id"],
                        $remappedGeometryIds,
                        $remappedPersistentGeometryIds
                    );
                    $grid['sockets'][] = $socketData;
                }

                $sources = $this->getDatabase()->query(
                    "SELECT grid_source_geometry_id as geometry_id FROM grid_source WHERE grid_source_grid_id = ?",
                    array($grid['grid_id'])
                );
                foreach ($sources as $source) {
                    $sourceData["geometry"] = $this->GetBaseGeometryInformation(
                        $source["geometry_id"],
                        $remappedGeometryIds,
                        $remappedPersistentGeometryIds
                    );
                    $grid['sources'][] = $sourceData;
                }
            }
        }


        $result = $plans;

        return count($errors) == 0;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Import(): void
    {
        $game = new Game();
        $config = $game->GetGameConfigValues();

        if (!isset($config['plans'])) {
            return;
        }

        $plans = $config['plans'];

        // self::Debug($plans);

        //Maps from old persistent ID to new persistent id. $array[$oldId] = newId;
        $importedPlanId = array();
        $importedLayerId = array();
        $importedGeometryId = array();
        $importedGridIds = array();

        foreach ($plans as $plan) {
            // self::Debug($plan);

            //create a new plan and get the new ID
            if (!isset($plan['plan_alters_energy_distribution'])) {
                $plan['plan_alters_energy_distribution'] = 0;
            }
            $planid = (int)$this->getDatabase()->query(
                "
                INSERT INTO plan (
                    plan_country_id, plan_name, plan_gametime, plan_lastupdate, plan_type,
                    plan_alters_energy_distribution, plan_state
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ",
                array(
                    $plan['plan_country_id'], $plan['plan_name'], $plan['plan_gametime'], 0, $plan['plan_type'],
                    $plan['plan_alters_energy_distribution'], "APPROVED"
                ),
                true
            );
            $importedPlanId[$plan['plan_id']] = $planid;

            if (isset($plan['fishing'])) {
                foreach ($plan['fishing'] as $fish) {
                    $this->getDatabase()->query(
                        "
                        INSERT INTO fishing (fishing_country_id, fishing_plan_id, fishing_type, fishing_amount)
                        VALUES (?, ?, ?, ?)
                        ",
                        array($fish['fishing_country_id'], $planid, $fish['fishing_type'], $fish['fishing_amount'])
                    );
                }
            }

            //Import messages for plan
            if (isset($plan['messages'])) {
                foreach ($plan['messages'] as $message) {
                    $this->getDatabase()->query(
                        "
                        INSERT INTO plan_message (
                              plan_message_plan_id, plan_message_country_id, plan_message_user_name, plan_message_text,
                              plan_message_time
                        ) VALUES (?, ?, ?, ?, ?)
                        ",
                        array(
                            $planid, $message["country_id"], $message["user_name"], $message["text"], $message["time"]
                        )
                    );
                }
            }

            //Import restriction settings;
            if (isset($plan['restriction_settings'])) {
                $this->ImportRestrictionSettingsForPlan($plan['restriction_settings'], $planid);
            }

            // self::Debug("new plan id: " . $planid);
            //Mapping of the latest id of a geometry. This maps from base geometry id to latest id inserted by the plan.

            foreach ($plan['layers'] as $layer) {
                //find the original layer ID from the current local database
                $original = $this->getDatabase()->query(
                    "SELECT layer_id FROM layer WHERE layer_name=?",
                    array($layer['name'])
                );

                if (!empty($original)) {
                    $original = $original[0]['layer_id'];

                    // self::Debug("Original layer id: " . $original);

                    //create a new layer for the new geometry
                    $lid = $this->getDatabase()->query(
                        "INSERT INTO layer
								(layer_original_id)
								VALUES (?)",
                        array($original),
                        true
                    );
                    $importedLayerId[$layer['layer_id']] = $lid;

                    // self::Debug("New layer id: " . $lid);

                    //add the new layer to the database
                    $this->getDatabase()->query(
                        "INSERT INTO plan_layer (plan_layer_plan_id, plan_layer_layer_id) VALUES (?, ?)",
                        array($planid, $lid)
                    );

                    foreach ($layer['geometry'] as $geometry) {
                        //add the geometry to the database
                        $geometryData = null;
                        if (isset($geometry['data'])) {
                            $geometryData = json_encode($geometry['data']);
                        }
                        $newGeometryId = $this->getDatabase()->query(
                            "
                            INSERT INTO geometry (
                                geometry_layer_id, geometry_FID, geometry_geometry, geometry_data, geometry_country_id,
                                geometry_type
                            ) VALUES (?, ?, ?, ?, ?, ?)
                            ",
                            array(
                                $lid, $geometry['FID'], $geometry['geometry'], $geometryData, $geometry['country'],
                                $geometry['type']
                            ),
                            true
                        );
                        $importedGeometryId[$geometry['geometry_id']] = $newGeometryId;

                        $baseGeometryId = $this->FixupPersistentGeometryID(
                            $geometry['base_geometry_info'],
                            $importedGeometryId
                        );

                        $this->getDatabase()->query(
                            "UPDATE geometry SET geometry_persistent = ? WHERE geometry_id = ?",
                            array($baseGeometryId, $newGeometryId)
                        );
                    }

                    //Import deleted geometry
                    foreach ($layer['deleted'] as $deletedGeometry) {
                        $deletedGeometryId = $this->FixupPersistentGeometryID(
                            $deletedGeometry['base_geometry_info'],
                            $importedGeometryId
                        );
                        if ($deletedGeometryId != -1) {
                            $this->getDatabase()->query(
                                "
                                INSERT INTO plan_delete (
                                    plan_delete_plan_id, plan_delete_geometry_persistent, plan_delete_layer_id
                                ) VALUES (?, ?, ?)
                                ",
                                array($planid, $deletedGeometryId, $lid)
                            );
                        }
                    }
                } else {
                    self::Debug("Could not find layer <strong>" . $layer['name'] . "</strong> in the database.");
                }
            }
            //update the persistent IDs or the client starts complaining
            $this->getDatabase()->query(
                "UPDATE geometry SET geometry_persistent=geometry_id WHERE geometry_persistent IS NULL"
            );

            //Import energy connections and output now we now all geometry is known by the importer.
            foreach ($plan['layers'] as $layer) {
                // So.. about this... We can probably speed this up quite a bit by building an array of all energy stuff
                //   that we still need to import and process it afterwards but currently this is not a bottleneck yet.
                foreach ($layer['geometry'] as $geometry) {
                    //Import energy connections
                    $newGeometryId = $this->FixupGeometryID($geometry['base_geometry_info'], $importedGeometryId);
                    if (!empty($geometry['cable'])) {
                        //self::Debug("Importing cable connection");
                        $startId = $this->FixupGeometryID($geometry['cable']['start'], $importedGeometryId);
                        $endId = $this->FixupGeometryID($geometry['cable']['end'], $importedGeometryId);
                        if ($startId != -1 && $endId != -1) {
                            $this->getDatabase()->query(
                                "
                                INSERT INTO energy_connection (
                                    energy_connection_start_id, energy_connection_end_id, energy_connection_cable_id,
                                   energy_connection_start_coordinates, energy_connection_lastupdate
                                ) VALUES (?, ?, ?, ?, 100)
                                ",
                                array($startId, $endId, $newGeometryId, $geometry['cable']['coordinates'])
                            );
                        }
                    }

                    //self::Debug($geometry);
                    //Import energy output
                    if (!empty($geometry['energy_output'])) {
                        //self::Debug("Importing energy output connection");
                        foreach ($geometry['energy_output'] as $output) {
                            $this->getDatabase()->query(
                                "
                                INSERT INTO energy_output (energy_output_geometry_id, energy_output_maxcapacity,
                                    energy_output_active
                                ) VALUES(?, ?, ?)
                                ",
                                array($newGeometryId, $output['maxcapacity'], $output['active'])
                            );
                        }
                    }
                }
            }

            //Import Energy grids
            if (!empty($plan['grids'])) {
                //self::Debug("Importing energy grid data");
                foreach ($plan['grids'] as $grid) {
                    $gridId = $this->getDatabase()->query(
                        "INSERT INTO grid (grid_name, grid_lastupdate, grid_active, grid_plan_id) VALUES(?, 100, ?, ?)",
                        array($grid['name'], $grid['active'], $planid),
                        true
                    );
                    $gridPersistent = $gridId;
                    if ($grid['grid_persistent'] == $grid['grid_id']) {
                        $importedGridIds[$grid['grid_persistent']] = $gridId;
                    } else {
                        if (isset($importedGridIds[$grid['grid_persistent']])) {
                            $gridPersistent = $importedGridIds[$grid['grid_persistent']];
                        } else {
                            self::Debug(
                                "Found reference persistent Grid ID (". $grid['grid_persistent'].
                                ") which has not been imported by the plans importer (yet)."
                            );
                        }
                    }
                    $this->getDatabase()->query(
                        "UPDATE grid SET grid_persistent = ? WHERE grid_id = ?",
                        array($gridPersistent, $gridId)
                    );

                    foreach ($grid['energy'] as $energy) {
                        $this->getDatabase()->query(
                            "
                            INSERT INTO grid_energy (
                                grid_energy_grid_id, grid_energy_country_id, grid_energy_expected
                            ) VALUES(?, ?, ?)
                            ",
                            array($gridId, $energy['country'], $energy['expected'])
                        );
                    }

                    foreach ($grid['removed'] as $removed) {
                        if (!empty($importedGridIds[$removed['grid_persistent']])) {
                            $this->getDatabase()->query(
                                "
                                INSERT INTO grid_removed (
                                    grid_removed_plan_id, grid_removed_grid_persistent
                                ) VALUES(?, ?)
                                ",
                                array($planid, $importedGridIds[$removed['grid_persistent']])
                            );
                        } else {
                            self::Debug(
                                "Found deleted Grid ID (". $removed['grid_persistent'].
                                ") which has not been imported by the plans importer (yet)."
                            );
                        }
                    }

                    if (!empty($grid['sockets'])) {
                        foreach ($grid['sockets'] as $socket) {
                            $geometryId = $this->FixupGeometryID($socket['geometry'], $importedGeometryId);
                            if ($geometryId != -1) {
                                $this->getDatabase()->query(
                                    "
                                    INSERT INTO grid_socket (grid_socket_grid_id, grid_socket_geometry_id) VALUES(?, ?)
                                    ",
                                    array($gridId, $geometryId)
                                );
                            }
                        }
                    }

                    if (!empty($grid['sources'])) {
                        foreach ($grid['sources'] as $source) {
                            $geometryId = $this->FixupGeometryID($source['geometry'], $importedGeometryId);
                            if ($geometryId != -1) {
                                $this->getDatabase()->query(
                                    "
                                    INSERT INTO grid_source (grid_source_grid_id, grid_source_geometry_id) VALUES(?, ?)
                                    ",
                                    array($gridId, $geometryId)
                                );
                            }
                        }
                    }
                }
            }

            $this->UpdatePlanConstructionTime($planid);
        }

        $this->ImportAllWarningsFromExportedPlans($plans, $importedPlanId, $importedLayerId);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function ExportGeometryForLayer(
        int $layerId,
        array &$remappedGeometryIds,
        array &$remappedPersistentGeometryIds
    ): array {
        /** @var array<array{geometry_id: int, geometry_FID: int, geometry_persistent: int, geometry: string, data: string, country: int, type: string, deleted: bool}> $geometryData */
        $geometryData = $this->getDatabase()->query(
            "SELECT
				geometry.geometry_id,
				geometry.geometry_FID as FID,
                geometry.geometry_persistent,
				geometry.geometry_geometry as geometry,
				geometry.geometry_data as data,
				geometry.geometry_country_id as country,
				geometry.geometry_type as type,
				geometry.geometry_deleted as deleted
            FROM geometry
			WHERE geometry.geometry_layer_id= ? ORDER BY geometry_id",
            array($layerId)
        );

        //We need to simplify the geometry data by throwing out all duplicate persistent IDs on the same layer.
        //Need to update the persistent ID of objects that rely on objects that we removed though.
        //So if an object is created and then updated in the same plan layer we need to propagate the persistent ID to
        //the later generation of that geometry and throw out the earlier versions.
        $latestIdForGeometryId = array();
        $createdInThisLayer = array();
        $geometryIdsToFixup = array();

        foreach ($geometryData as &$geom) {
            if ($geom['geometry_persistent'] == $geom['geometry_id']) {
                $createdInThisLayer[$geom['geometry_persistent']] = true;
            }

            $latestIdForGeometryId[$geom['geometry_persistent']] = $geom['geometry_id'];
        }

        $result = array();
        foreach ($geometryData as &$geom) {
            if ($latestIdForGeometryId[$geom['geometry_persistent']] == $geom['geometry_id']) {
                //If this persistent ID was created in this layer update the base geometry info.
                if (array_key_exists($geom['geometry_persistent'], $createdInThisLayer)) {
                    $remappedPersistentGeometryIds[$geom['geometry_persistent']] = $geom['geometry_id'];
                }

                if (array_key_exists($geom['geometry_persistent'], $geometryIdsToFixup)) {
                    foreach ($geometryIdsToFixup[$geom['geometry_persistent']] as $flattenedGeometryId) {
                        $remappedGeometryIds[$flattenedGeometryId] = $geom['geometry_id'];
                    }
                }

                $geom['base_geometry_info'] = $this->GetBaseGeometryInformation(
                    $geom['geometry_id'],
                    $remappedGeometryIds,
                    $remappedPersistentGeometryIds
                );
                $result[] = $geom;
            } else {
                $geometryIdsToFixup[$geom['geometry_persistent']][] = $geom['geometry_id'];
            }
        }

        //Filter out any deleted geometry. Only last generation will be deleted.
        for ($i = count($result) - 1; $i >= 0; --$i) {
            if ($result[$i]["deleted"] == 1) {
                array_splice($result, $i, 1);
            } else {
                unset($result[$i]["deleted"]);
            }
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ExportWarningsForLayer(int $layerId): array
    {
        return $this->getDatabase()->query("SELECT
				warning_issue_type as issue_type,
				warning_x as x,
				warning_y as y,
				warning_source_plan_id as source_plan_id,
				restriction.restriction_message as restriction_message
			FROM warning
			INNER JOIN restriction ON restriction.restriction_id = warning.warning_restriction_id
			WHERE warning_layer_id = ? AND warning_active = 1", array($layerId));
    }

    /**
     * Returns the database id of the persistent geometry id described by the base_geometry_info
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function FixupPersistentGeometryID(array $baseGeometryInfo, array $mappedGeometryIds): int
    {
        $fixedGeometryId = -1;
        if (array_key_exists("geometry_mspid", $baseGeometryInfo) && !empty($baseGeometryInfo["geometry_mspid"])) {
            $fixedGeometryId = $this->GetGeometryIdByMspId($baseGeometryInfo["geometry_mspid"]);
        } else {
            if (array_key_exists($baseGeometryInfo["geometry_persistent"], $mappedGeometryIds)) {
                $fixedGeometryId = $mappedGeometryIds[$baseGeometryInfo["geometry_persistent"]];
            } else {
                self::Debug(
                    "Found geometry ID (Fallback field \"geometry_persistent\": ".
                    $baseGeometryInfo["geometry_persistent"].
                    ") which is not referenced by msp id and hasn't been imported by the plans importer yet. ".
                    var_export($baseGeometryInfo, true)
                );
            }
        }
        return $fixedGeometryId;
    }

    /**
     * Returns the database id of the geometry id described by the base_geometry_info
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function FixupGeometryID(array $baseGeometryInfo, array $mappedGeometryIds): int
    {
        $fixedGeometryId = -1;
        if (array_key_exists($baseGeometryInfo["geometry_id"], $mappedGeometryIds)) {
            $fixedGeometryId = $mappedGeometryIds[$baseGeometryInfo["geometry_id"]];
        } else {
            // If we can't find the geometry id in the ones that we already have imported, check if the geometry id
            //   matches the persistent id, and if so select it by the mspid since this should all be present then.
            if ($baseGeometryInfo["geometry_id"] == $baseGeometryInfo["geometry_persistent"]) {
                if (isset($baseGeometryInfo["geometry_mspid"])) {
                    $fixedGeometryId = $this->GetGeometryIdByMspId($baseGeometryInfo["geometry_mspid"]);
                } else {
                    self::Debug(
                        "Found geometry (".implode(", ", $baseGeometryInfo).
                        " which has not been imported by the plans importer. The persistent id matches but mspid is".
                        "not set."
                    );
                }
            } else {
                self::Debug(
                    "Found geometry ID (Fallback field \"geometry_id\": ". $baseGeometryInfo["geometry_id"].
                    ") which hasn't been imported by the plans importer yet."
                );
            }
        }
        return $fixedGeometryId;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetGeometryIdByMspId(int $mspId): int
    {
        $result = $this->getDatabase()->query(
            "SELECT geometry_id FROM geometry WHERE geometry_mspid = ?",
            array($mspId)
        );
        if (count($result) > 0) {
            return $result[0]["geometry_id"];
        } else {
            self::Warning("Could not find MSP ID ".$mspId." in the current database");
            return -1;
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ImportAllWarningsFromExportedPlans(
        array $plans,
        array $planTranslationTable,
        array $layerTranslationTable
    ): void {
        foreach ($plans as &$plan) {
            //Any plan that is a starting plan don't import the warnings / errors for please.
            //Basically a QoL improvement for Wilco's workflow, since we don't care about errors in starting plans.
            if ($plan["plan_gametime"] < 0) {
                continue;
            }

            foreach ($plan['layers'] as &$layer) {
                $newLayerId = $layerTranslationTable[$layer['layer_id']];

                /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
                foreach ($layer['warnings'] as &$warning) {
                    $restrictionId = $this->getDatabase()->query(
                        "SELECT restriction_id FROM restriction WHERE restriction_message = ?",
                        array($warning['restriction_message'])
                    );
                    if (count($restrictionId) == 0) {
                        self::Debug(
                            "Could not find restriction id for message \"".$warning['restriction_message']."\""
                        );
                        continue;
                    }
                    $warningSourcePlan = $planTranslationTable[$warning['source_plan_id']];

                    $this->getDatabase()->query(
                        "
                        INSERT INTO warning (
                            warning_active, warning_last_update, warning_layer_id, warning_issue_type, warning_x,
                            warning_y, warning_source_plan_id, warning_restriction_id
                        ) VALUES(1, 100, ?, ?, ?, ?, ?, ?)
                        ",
                        array(
                            $newLayerId, $warning['issue_type'], $warning['x'], $warning['y'], $warningSourcePlan,
                            $restrictionId[0]['restriction_id']
                        )
                    );
                }
                unset($warning);
            }
            unset($layer);
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function ExportRestrictionSettingsForPlan(int $planId): array
    {
        return $this->getDatabase()->query(
            "
            SELECT plan_restriction_area_country_id as country_id,
				plan_restriction_area_entity_type as entity_type_id,
				plan_restriction_area_size as size,
				layer.layer_name
			FROM plan_restriction_area
			INNER JOIN layer ON plan_restriction_area_layer_id = layer.layer_id
			WHERE plan_restriction_area_plan_id = ?
			",
            array($planId)
        );
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function ImportRestrictionSettingsForPlan(array $restrictionSettings, int $planId): void
    {
        /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
        foreach ($restrictionSettings as &$setting) {
            $layerId = $this->getDatabase()->query(
                "SELECT layer_id FROM layer WHERE layer_name =?",
                array($setting['layer_name'])
            );
            if (count($layerId) == 0) {
                self::Debug(
                    "Could not find layer with name ".$setting['layer_name'].
                    " when importing restriction settings. Settings referencing this layer will be dropped."
                );
                continue;
            }

            $this->getDatabase()->query(
                "
                INSERT INTO plan_restriction_area (
                    plan_restriction_area_plan_id, plan_restriction_area_layer_id, plan_restriction_area_country_id,
                    plan_restriction_area_entity_type, plan_restriction_area_size
                ) VALUES(?, ?, ?, ?, ?)
                ",
                array(
                    $planId, $layerId[0]['layer_id'], $setting['country_id'], $setting['entity_type_id'],
                    $setting['size']
                )
            );
        }
    }

    /**
     * @throws Exception
     */
    public function messageAsync(int $plan, int $teamId, string $userName, string $text): PromiseInterface
    {
        return $this->getAsyncDatabase()->insert(
            'plan_message',
            [
                'plan_message_plan_id' => $plan,
                'plan_message_country_id' => $teamId,
                'plan_message_user_name' => $userName,
                'plan_message_text' => $text,
                'plan_message_time' => microtime(true)
            ]
        );
    }

    /**
     * @apiGroup Plan
     * @apiDescription Add a message to a plan
     * @throws Exception
     * @api {POST} /plan/message Message
     * @apiParam {int} plan Plan id that this message applies to.
     * @apiParam {int} team_id Team (Country) ID that this message originated from.
     * @apiParam {string} user_name Display name of the user that sent this message.
     * @apiParam {string} text Message sent by the user
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Message(int $plan, int $team_id, string $user_name, string $text): void
    {
        await($this->messageAsync($plan, $team_id, $user_name, $text));
    }

    /**
     * @apiGroup Plan
     * @apiDescription Lock a plan
     * @throws Exception
     * @api {POST} /plan/lock Lock
     * @apiParam {int} plan plan id
     * @apiSuccess {int} success 1
     * @apiSuccess {int} failure -1
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Lock(int $id, int $user): void
    {
        $changedRows = $this->getDatabase()->queryReturnAffectedRowCount(
            "UPDATE plan SET plan_lock_user_id=?, plan_lastupdate=? WHERE plan_id=? AND plan_lock_user_id IS NULL",
            array($user, microtime(true), $id)
        );
        if ($changedRows != 1) {
            throw new Exception(
                "Lock of plan ".$id." for user ".$user." failed. Perhaps it was already or still locked?"
            );
        }
    }

    /**
     * @apiGroup Plan
     * @apiDescription Rename a plan
     * @throws Exception
     * @api {POST} /plan/name Name
     * @apiParam {int} id plan id
     * @apiParam {string} name new plan name
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Name(int $id, string $name): ?PromiseInterface
    {
        $this->getDatabase()->query(
            "UPDATE plan SET plan_name=?, plan_lastupdate=? WHERE plan_id=?",
            array($name, microtime(true), $id)
        );

        // @todo: fake it till you make it... but fix it later!
        if ($this->isAsync()) {
            return resolveOnFutureTick(new Deferred())->promise();
        }
        return null;
    }

    /**
     * @apiGroup Plan
     * @apiDescription Change plan date
     * @throws Exception
     * @api {POST} /plan/date Date
     * @apiParam {int} id plan id
     * @apiParam {int} date new plan date
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Date(int $id, int $date): ?PromiseInterface
    {
        $this->getDatabase()->query(
            "UPDATE plan SET plan_gametime=?, plan_lastupdate=? WHERE plan_id=?",
            array($date, microtime(true), $id)
        );
        $this->UpdatePlanConstructionTime($id);

        // @todo: fake it till you make it... but fix it later!
        if ($this->isAsync()) {
            return resolveOnFutureTick(new Deferred())->promise();
        }
        return null;
    }

    /**
     * @apiGroup Plan
     * @apiDescription Update the description
     * @throws Exception
     * @api {POST} /plan/description Description
     * @apiParam {int} id plan id
     * @apiParam {string} description new plan description
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Description(int $id, string $description = ""): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('plan')
                ->set('plan_description', $qb->createPositionalParameter($description))
                ->set('plan_lastupdate', $qb->createPositionalParameter(microtime(true)))
                ->where($qb->expr()->eq('plan_id', $qb->createPositionalParameter($id)))
        )
        ->done(
            function (/* Result $result */) use ($deferred) {
                $deferred->resolve(); // return void, we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Plan
     * @apiDescription Update the plan type
     * @throws Exception
     * @api {POST} /plan/type Type
     * @apiParam {int} id plan id
     * @apiParam {string} type comma separated string of the plan types, values can be "ecology", "shipping" or
     *   "energy" (e.g. "ecology,energy"). Empty if none apply
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Type(int $id, string $type): void
    {
        $this->getDatabase()->query(
            "UPDATE plan SET plan_type=?, plan_lastupdate=? WHERE plan_id=?",
            array($type, microtime(true), $id)
        );
    }

    /**
     * @apiGroup Plan
     * @apiDescription Updates or sets the restrction area sizes for this plan.
     * @throws Exception
     * @api {POST} /plan/SetRestrictionAreas Set Restriction Areas
     * @apiParam {int} plan_id Plan Id
     * @apiParam {array} settings Json array restriction area settings
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetRestrictionAreas(int $plan_id, array $settings): ?PromiseInterface
    {
        foreach ($settings as $setting) {
            $this->getDatabase()->query(
                "
                INSERT INTO plan_restriction_area (
                    plan_restriction_area_plan_id, plan_restriction_area_layer_id, plan_restriction_area_country_id,
                    plan_restriction_area_entity_type, plan_restriction_area_size
                ) VALUES(?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE plan_restriction_area_size = ?
                ",
                array(
                    $plan_id, $setting["layer_id"], $setting["team_id"], $setting["entity_type_id"],
                    $setting["restriction_size"], $setting["restriction_size"]
                )
            );
        }
        $this->getDatabase()->query(
            "UPDATE plan SET plan_lastupdate = ? WHERE plan_id = ?",
            array(microtime(true), $plan_id)
        );

        // @todo: fake it till you make it... but fix it later!
        if ($this->isAsync()) {
            return resolveOnFutureTick(new Deferred())->promise();
        }
        return null;
    }

    /**
     * @apiGroup Plan
     * @apiDescription Unlock a plan
     * @throws Exception
     * @api {POST} /plan/unlock Unlock
     * @apiParam {int} plan plan id
     * @apiParam {int} force_unlock (0|1) Force unlock a plan. Don't check for the correct user, just do it.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Unlock(int $id, int $user, int $force_unlock = 0): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->select('plan_lock_user_id')
                ->from('plan')
                ->where('plan_id = ' . $qb->createPositionalParameter($id))
        )
        ->then(function (Result $result) use ($id, $user, $force_unlock) {
            $row = $result->fetchFirstRow();
            if (empty($row['plan_lock_user_id'])) {
                return null; // no need to return an exception or do anything, the plan is just already unlocked.
            }

            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            $qb->update('plan')
                ->set('plan_lock_user_id', $qb->createPositionalParameter(null))
                ->set('plan_lastupdate', $qb->createPositionalParameter(microtime(true)));
            $where = $qb->expr()->and($qb->expr()->eq('plan_id', $id));
            if ($force_unlock == 0) {
                $where = $where->with($qb->expr()->eq('plan_lock_user_id', $qb->createPositionalParameter($user)));
            }
            $qb->where($where);
            return $this->getAsyncDatabase()->query($qb);
        })
        ->done(
            function (/* ?Result $result */) use ($deferred) {
                $deferred->resolve(); // return void, we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise =  $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Plan
     * @apiDescription Get all layer restrictions
     * @throws Exception
     * @api {POST} /plan/restrictions Restrictions
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Restrictions(): array
    {
        $game = new Game();
        $gameConfig = $game->GetGameConfigValues();

        $result = array();
        $result['restriction_point_size'] = (isset($gameConfig["restriction_point_size"]))?
            $gameConfig["restriction_point_size"] : 5.0;
        $result['restrictions'] = $this->getDatabase()->query(
            "
            SELECT
				restriction_id as id,
				restriction_start_layer_id as start_layer,
				restriction_start_layer_type as start_type,
				restriction_sort as sort,
				restriction_type as type,
				restriction_message as message,
				restriction_end_layer_id as end_layer,
				restriction_end_layer_type as end_type,
				restriction_value as value
            FROM restriction
            "
        );

        return $result;
    }

    /**
     * @apiGroup Plan
     * @throws Exception
     * @api {POST} /plan/GetInitialFishingValues GetInitialFishingValues
     * @apiDescription Returns the initial fishing values submitted by MEL. The values are in a 0..1 range for each
     *   fishing fleet and country. Fishing fleet values summed together should be in the range of 0..1
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetInitialFishingValues(): array
    {
        //Well this is going to be an amazing ride.
        // So we need to select the values associated with a default fishing plan which is the newest one generated
        //   because there can be multiple.
        $sourcePlanId = $this->getDatabase()->query(
            "
            SELECT plan.plan_id
            FROM plan
            WHERE plan.plan_country_id = 1 AND plan.plan_gametime = -1 AND (
                plan.plan_state = 'APPROVED' OR plan.plan_state = 'IMPLEMENTED'
            ) ORDER BY plan.plan_id DESC
            "
        );

        $initialData = array();
        if (count($sourcePlanId) > 0) {
            //Then we select the data
            $initialData = $this->getDatabase()->query(
                "
                SELECT
					fishing.fishing_type as type,
					fishing.fishing_country_id as country_id,
					fishing.fishing_amount as amount
				FROM fishing
					INNER JOIN plan ON plan.plan_id = fishing.fishing_plan_id
				WHERE fishing.fishing_plan_id = ?",
                array($sourcePlanId[0]["plan_id"])
            );
        }

        return $initialData;
    }

    /**
     * @apiGroup Plan
     * @throws Exception
     * @api {POST} /plan/fishing Fishing
     * @apiParam {int} plan plan id
     * @apiParam {array} fishing_values JSON encoded key value pair array of fishing values
     * @apiDescription Sets the fishing values for a plan to the fishing_values included in the call. Will delete
     *   all fishing values that existed before this plan.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Fishing(int $plan, array $fishing_values): void
    {
        $this->DeleteFishing($plan);

        foreach ($fishing_values as $fishingValues) {
            $this->getDatabase()->query(
                "
                INSERT INTO fishing (
                    fishing_country_id, fishing_type, fishing_amount, fishing_plan_id
                ) VALUES (?, ?, ?, ?)
                ",
                array($fishingValues['country_id'], $fishingValues['type'], $fishingValues['amount'], $plan)
            );
        }

        $this->getDatabase()->query(
            "UPDATE plan SET plan_lastupdate=? WHERE plan_id=?",
            array(microtime(true), $plan)
        );
    }

    /**
     * @apiGroup Plan
     * @throws Exception
     * @api {POST} /plan/DeleteFishing Delete Fishing
     * @apiParam {int} plan plan id
     * @apiDescription delete all the fishing settings associated with a plan
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteFishing(int $plan): void
    {
        $this->getDatabase()->query("DELETE FROM fishing WHERE fishing_plan_id=?", array($plan));
    }

    /**
     * @apiGroup Plan
     * @throws Exception
     * @api {POST} /plan/SetEnergyError Set Energy Error
     * @apiParam {int} id plan id
     * @apiParam {int} error error boolean [0|1]
     * @apiParam {int} check_dependent_plans boolean [0|1] Check dependent plans and set them to error as well?
     *   Only works when setting plans to error 1
     * @apiDescription set the energy error flag of a single plan
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetEnergyError(int $id, int $error, int $check_dependent_plans = 0): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->select('plan_name')
                ->from('plan')
                ->where($qb->expr()->eq('plan_id', $qb->createPositionalParameter($id)))
        )
        ->then(function (Result $result) use ($error, $id, $check_dependent_plans) {
            $planData = $result->fetchFirstRow();
            $planName = $planData['plan_name'] ?? 'UNKNOWN';
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->update('plan')
                    ->set('plan_energy_error', $qb->createPositionalParameter($error))
                    ->set('plan_lastupdate', $qb->createPositionalParameter(microtime(true)))
                    ->where($qb->expr()->eq('plan_id', $id))
            )
            ->then(function (/* Result $result */) use ($error, $id, $planName, $check_dependent_plans) {
                if ($error == 1 && $check_dependent_plans == 1) {
                    $this->setAllDependentEnergyPlansToError($id, $planName);
                }
                return null;
            });
        })
        ->done(
            /** @var null $dummy */
            function (/* $dummy */) use ($deferred) {
                $deferred->resolve(); // return void, we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Plan
     * @throws Exception
     * @api {POST} /plan/SetEnergyDistribution Set Energy Distribution
     * @apiParam {int} id plan id
     * @apiParam {int} alters_energy_distribution boolean [0|1]
     * @apiDescription set the energy distribution flag of a single plan
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetEnergyDistribution(int $id, bool $alters_energy_distribution): void
    {
        $this->getDatabase()->query(
            "UPDATE plan SET plan_alters_energy_distribution=? WHERE plan_id=?",
            array($alters_energy_distribution, $id)
        );
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ImportRestrictions(): void
    {
        $game = new Game();
        $fullConfig = $game->GetGameConfigValues();
        $config = $fullConfig['restrictions'];

        if (!is_array($config)) {
            self::Warning("No restrictions found in the current config file.");
            return;
        }

        foreach ($config as $restrictionObj) {
            foreach ($restrictionObj as $restriction) {
                $layerStart = $this->getDatabase()->query(
                    "SELECT layer_id FROM layer WHERE layer_name=?",
                    array($restriction['startlayer'])
                );

                if (empty($layerStart)) {
                    self::Warning(
                        "<strong>" . $restriction['startlayer'] .
                        "</strong> does not exist in this config file. Is it added in the layer meta?"
                    );
                    continue;
                }

                $startId = $layerStart[0]['layer_id'];

                $layerEnd = $this->getDatabase()->query(
                    "SELECT layer_id FROM layer WHERE layer_name=?",
                    array($restriction['endlayer'])
                );

                if (empty($layerEnd)) {
                    self::Warning(
                        "<strong>" . $restriction['endlayer'] .
                        "</strong> does not exist in this config file. Is it added in the layer meta?"
                    );
                    continue;
                }

                $endId = $layerEnd[0]['layer_id'];
                $this->getDatabase()->query(
                    "
                    INSERT INTO restriction (
                        restriction_start_layer_id, restriction_start_layer_type, restriction_sort,
                        restriction_value, restriction_type, restriction_message,
                        restriction_end_layer_id, restriction_end_layer_type
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ",
                    array(
                        $startId,
                        $restriction['starttype'],
                        $restriction['sort'],
                        $restriction['value'],
                        $restriction['type'],
                        $restriction['message'],
                        $endId,
                        $restriction['endtype']
                    )
                );
            }
        }

        //Create a type unavailable for all layer types that have an available from date.
        foreach ($fullConfig["meta"] as $layerId => $layerMeta) {
            foreach ($layerMeta["layer_type"] as $typeId => $typeMeta) {
                if (isset($typeMeta["availability"]) && (int)$typeMeta["availability"] > 0) {
                    $this->getDatabase()->query(
                        "
                        INSERT INTO restriction (
                            restriction_start_layer_id, restriction_start_layer_type, restriction_sort,
                            restriction_type, restriction_message, restriction_end_layer_id,
                            restriction_end_layer_type, restriction_value
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ",
                        array(
                            $layerId,
                            $typeId,
                            "TYPE_UNAVAILABLE",
                            "ERROR",
                            "Type is not available yet at the plan implementation time.",
                            $layerId,
                            $typeId,
                            0
                        )
                    );
                }
            }
        }
    }
}
