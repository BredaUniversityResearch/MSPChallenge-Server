<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\API\v1\Game;
use App\Domain\Common\CommonBase;
use App\Domain\Common\ToPromiseFunction;
use Drift\DBAL\Result;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function App\parallel;
use function App\resolveOnFutureTick;
use function App\tpf;

class GameLatest extends CommonBase
{
    private const CONTEXT_PARAM_NEW_TIME = 'newTime';
    private const CONTEXT_PARAM_LAST_UPDATE_TIME = 'lastUpdateTime';
    private const CONTEXT_PARAM_TEAM_ID = 'teamId';
    private const CONTEXT_PARAM_USER = 'user';

    private bool $allowEnergyKpiUpdate = true;

    private function newSimulationDataAvailable(array $tickData, float $lastUpdateTime): bool
    {
        if (($tickData['mel_lastupdate'] > $lastUpdateTime) ||
            ($tickData['cel_lastupdate'] > $lastUpdateTime) ||
            ($tickData['sel_lastupdate'] > $lastUpdateTime)) {
            return true;
        }
        return false;
    }

    /**
     * @param array $context
     * @param array $data
     * @return ToPromiseFunction[]
     * @throws Exception
     */
    private function parallelTasks(array $context, array &$data): array
    {
        $latestMessages = $this->getLatestMessages($context)
            ->then(function (array $result) use (&$data) {
                $data['planmessages'] = $result;
            });
        $latestRaster = $this->getLatestRaster($context)
            ->then(function (Result $result) use (&$data) {
                $data['raster'] = $result->fetchAllRows();
            });
        $latestEnergy = $this->getLatestEnergy($context)
            ->then(function (array $energyData) use (&$data) {
                $data['policy_updates'][] = array_merge([
                    'policy_type' => 'energy',
                ], $energyData);
            });
        $latestKpi = $this->getLatestKpi($context)
            ->then(function (array $results) use (&$data) {
                $data['simulation_updates'] = [
                    ['simulation_type' => 'CEL', 'kpi' => $results['energy']],
                    ['simulation_type' => 'MEL', 'kpi' => $results['ecology']],
                    ['simulation_type' => 'SEL', 'kpi' => $results['shipping']]
                ];
            });
        $latestWarning = $this->getLatestWarning($context)
            ->then(function (Result $queryResult) use (&$data) {
                $data['simulation_updates'][2]['shipping_issues'] = $queryResult->fetchAllRows();
            });
        $latestObjective = $this->getLatestObjective($context)
            ->then(function (Result $result) use (&$data) {
                $data['objectives'] = $result->fetchAllRows();
            });
        $latestPlan = $this->getLatestPlan($context)
            ->then(function (array $planData) use ($context, &$data) {
                $data['plan'] = $planData;
                return $this->getLatestPlanLayers($planData, $context)
                ->then(function (array $layersContainer) use ($context, &$data) {
                    foreach ($data['plan'] as $key => &$p) {
                        //only send the geometry when it's required
                        $p['layers'] = $layersContainer[$key];
                        if ((
                            $p['state'] == "DESIGN" && $p['previousstate'] == "CONSULTATION" &&
                            $p['country'] != $context[self::CONTEXT_PARAM_TEAM_ID]
                        )) {
                            $p['active'] = 0;
                        }
                    }
                    unset($p);
                });
            });
        return [
            tpf(fn() => $latestPlan),
            tpf(fn() => $latestMessages),
            tpf(fn() => $latestRaster),
            tpf(fn() => $latestEnergy),
            tpf(fn() => $latestKpi),
            tpf(fn() => $latestWarning),
            tpf(fn() => $latestObjective)
        ];
    }

    /**
     * Gets the latest plans & messages from the server
     *
     * @param int $teamId
     * @param float $lastUpdateTime
     * @param int $user
     * @param bool $showDebug
     * @return ?PromiseInterface
     * @throws Exception
     */
    public function latest(int $teamId, float $lastUpdateTime, int $user, bool $showDebug = false): ?PromiseInterface
    {
        return $this->calculateUpdatedTime(
            $showDebug
        )
        ->then(function (array $tick) use ($teamId, $lastUpdateTime, $user) {
            $game = new Game();
            $this->asyncDataTransferTo($game);
            $this->allowEnergyKpiUpdate =
                (
                    $game->areSimulationsUpToDate($tick) &&
                    $this->newSimulationDataAvailable($tick, $lastUpdateTime)
                ) ||
                $lastUpdateTime < PHP_FLOAT_EPSILON; // first client update
            $context = [
                self::CONTEXT_PARAM_NEW_TIME => microtime(true),
                self::CONTEXT_PARAM_LAST_UPDATE_TIME => $lastUpdateTime,
                self::CONTEXT_PARAM_TEAM_ID => $teamId,
                self::CONTEXT_PARAM_USER => $user
            ];
            $data = array();
            $data['tick'] = $tick;
            $data['prev_update_time'] = $lastUpdateTime;
            //Add a slight fudge of 1ms to the update times to avoid rounding issues.
            $data['update_time'] = $context[self::CONTEXT_PARAM_NEW_TIME] - 0.001;
            return parallel($this->parallelTasks($context, $data))
                ->then(function (/* array $results */) use ($context, &$data) {
                    // send an empty string if nothing was updated
                    if (empty($data['energy']['connections']) &&
                        empty($data['energy']['output']) &&
                        empty($data['geometry']) &&
                        empty($data['plan']) &&
                        empty($data['messages']) &&
                        empty($data['planmessages']) &&
                        empty($data['kpi']) &&
                        empty($data['warning']) &&
                        empty($data['raster']) &&
                        empty($data['objectives'])) {
                        return resolveOnFutureTick(new Deferred(), [])->promise();
                    }
                    return $this->getAsyncDatabase()->update(
                        'user',
                        [
                            'user_id' => $context[self::CONTEXT_PARAM_USER]
                        ],
                        [
                            'user_lastupdate' => $context[self::CONTEXT_PARAM_NEW_TIME]
                        ]
                    );
                })
                ->then(
                    function (/* array $result */) use (&$data) {
                        return $data;
                    }
                );
        })
        // add debug data to payload, only to be dumped to log, see PluginHelper::dump()
        ->then(function (array &$data) use ($lastUpdateTime) {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select(
                        'api_batch_id',
                        'api_batch_state',
                        'api_batch_country_id',
                        'api_batch_user_id',
                        'api_batch_communicated'
                    )
                    ->from('api_batch')
                    ->where($qb->expr()->gt('api_batch_lastupdate', $qb->createPositionalParameter($lastUpdateTime)))
            )
            ->then(function (Result $result) use (&$data) {
                $data['debug']['batches'] = $result->fetchAllRows() ?: [];
                return $data;
            });
        });
    }

    /**
     * @throws Exception
     */
    private function calculateUpdatedTime(bool $showDebug = false): PromiseInterface
    {
        return $this->getAsyncDatabase()->query(
            $this->getAsyncDatabase()->createQueryBuilder()
                ->select(
                    'game_state as state',
                    'game_lastupdate as lastupdate',
                    'game_currentmonth as month',
                    'game_start as start',
                    'game_planning_gametime as era_gametime',
                    'game_planning_realtime as era_realtime',
                    'game_planning_era_realtime as planning_era_realtime',
                    'game_planning_monthsdone as era_monthsdone',
                    'game_mel_lastmonth as mel_lastmonth',
                    'game_cel_lastmonth as cel_lastmonth',
                    'game_sel_lastmonth as sel_lastmonth',
                    'game_mel_lastupdate as mel_lastupdate',
                    'game_cel_lastupdate as cel_lastupdate',
                    'game_sel_lastupdate as sel_lastupdate',
                    'game_eratime as era_time'
                )
                ->from('game')
                ->setMaxResults(1)
        )
        ->then(function (Result $result) use ($showDebug) {
            $assureGameLatestUpdate = new Deferred();
            $tick = $result->fetchFirstRow();
            //only update if the game is playing
            if (!in_array($tick['state'], ['END', 'PAUSE', 'SETUP']) && $tick['lastupdate'] == 0) {
                //if the last update was at time 0, this is the very first tick happening for this game
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                $this->getAsyncDatabase()->query(
                    $qb
                        ->update('game')
                        ->set('game_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
                )
                ->done(
                    function (Result $result) use (&$tick, $assureGameLatestUpdate) {
                        $tick['lastupdate'] = microtime(true);
                        $assureGameLatestUpdate->resolve();
                    },
                    function ($reason) use ($assureGameLatestUpdate) {
                        $assureGameLatestUpdate->reject($reason);
                    }
                );
            } else {
                $assureGameLatestUpdate->resolve();
            }
            return $assureGameLatestUpdate->promise()
                ->then(function () use ($tick, $showDebug) {
                    $state = $tick["state"];
                    $secondsPerMonth = $tick['era_realtime'] / $tick['era_gametime'];

                    //only update if the game is playing
                    if ($state != "END" && $state != "PAUSE" && $state != "SETUP") {
                        $currentTime = microtime(true);
                        $lastUpdate = $tick['lastupdate'];
                        assert($lastUpdate != 0);

                        $diff = $currentTime - $lastUpdate;
                        $secondsPerMonth = $tick['era_realtime'] / $tick['era_gametime'];

                        if ($diff < $secondsPerMonth) {
                            $tick['era_timeleft'] = $tick['era_realtime'] - $diff -
                                ($tick['era_monthsdone'] * $secondsPerMonth);
                        } else {
                            $tick['era_timeleft'] = -1;
                        }

                        if ($showDebug) {
                            wdo("diff: " . $diff, OutputInterface::VERBOSITY_VERY_VERBOSE);
                        }

                        if ($showDebug) {
                            wdo("timeleft: " . $tick['era_timeleft'], OutputInterface::VERBOSITY_VERY_VERBOSE);
                        }
                    } elseif ($state == "PAUSE" || $state == "SETUP") {
                        //[MSP-1116] Seems sensible?
                        $tick['era_timeleft'] = $tick['era_realtime'] - ($tick['era_monthsdone'] * $secondsPerMonth);
                        if ($showDebug) {
                            wdo('GAME PAUSED', OutputInterface::VERBOSITY_VERY_VERBOSE);
                        }
                    } else {
                        if ($showDebug) {
                            wdo('GAME ENDED', OutputInterface::VERBOSITY_VERY_VERBOSE);
                        }
                    }

                    if ($showDebug) {
                        wdo('Tick: ' . PHP_EOL . json_encode($tick));
                    }

                    return $tick;
                });
        });
    }

    /**
     * get plan incl. its layers
     *
     * @throws Exception
     */
    private function getLatestPlan(array $context): PromiseInterface
    {
        $plan = new PlanLatest();
        $this->asyncDataTransferTo($plan);
        return $plan->latest((int)$context[self::CONTEXT_PARAM_LAST_UPDATE_TIME])
            ->then(function (array $planData) use ($context) {
                if (($_ENV['DEBUG_PERF_TIMING'] ?? null) !== null) {
                    wdo((microtime(true) - $context[self::CONTEXT_PARAM_NEW_TIME]) . ' elapsed after plan<br />');
                }
                return $planData;
            });
    }

    /**
     * get all the plan's additional layer data incl. geometry
     *
     * @throws Exception
     */
    private function getLatestPlanLayers(array $planData, array $context): PromiseInterface
    {
        $layer = new LayerLatest();
        $this->asyncDataTransferTo($layer);
        $toPromiseFunctions = [];
        foreach ($planData as $p) {
            $toPromiseFunctions[] = tpf(function () use ($layer, $p, $context) {
                return $layer->latest($p['layers'], $context[self::CONTEXT_PARAM_LAST_UPDATE_TIME], $p['id']);
            });
        }
        return parallel(
            $toPromiseFunctions
        )
        ->then(function (array $layersContainer) use ($context) {
            if (($_ENV['DEBUG_PERF_TIMING'] ?? null) !== null) {
                wdo((microtime(true) - $context[self::CONTEXT_PARAM_NEW_TIME]) . ' elapsed after plan layers<br />');
            }
            return $layersContainer;
        });
    }

    /**
     * @throws Exception
     */
    private function getLatestMessages(array $context): PromiseInterface
    {
        $plan = new PlanLatest();
        $this->asyncDataTransferTo($plan);
        return $plan->getMessages(
            $context[self::CONTEXT_PARAM_LAST_UPDATE_TIME]
        )
        ->then(function (array $result) use ($context) {
            if (($_ENV['DEBUG_PERF_TIMING'] ?? null) !== null) {
                wdo((microtime(true) - $context[self::CONTEXT_PARAM_NEW_TIME]) . ' elapsed after plan messages<br />');
            }
            return $result;
        });
    }

    /**
     * @throws Exception
     */
    private function getLatestRaster(array $context): PromiseInterface
    {
        //return any raster layers that need to be updated
        $layer = new LayerLatest();
        $this->asyncDataTransferTo($layer);
        return $layer->latestRaster(
            $context[self::CONTEXT_PARAM_LAST_UPDATE_TIME]
        )
        ->then(function (Result $result) use ($context) {
            if (($_ENV['DEBUG_PERF_TIMING'] ?? null) !== null) {
                wdo((microtime(true) - $context[self::CONTEXT_PARAM_NEW_TIME]) . ' elapsed after raster<br />');
            }
            return $result;
        });
    }

    /**
     * @throws Exception
     */
    private function getLatestEnergy(array $context): PromiseInterface
    {
        ;
        $energy = new EnergyLatest();
        $this->asyncDataTransferTo($energy);
        $deferred = new Deferred();
        $energy->fetchAll($this->allowEnergyKpiUpdate)->then(function (array $queryResults) use ($deferred) {
            $energyData['connections'] = $queryResults[0]->fetchAllRows();
            $energyData['output'] = $queryResults[1]->fetchAllRows();
            $deferred->resolve($energyData);
        });
        return $deferred->promise()
            ->then(function (array $energyData) use ($context) {
                if (($_ENV['DEBUG_PERF_TIMING'] ?? null) !== null) {
                    wdo((microtime(true) - $context[self::CONTEXT_PARAM_NEW_TIME]) . ' elapsed after energy<br />');
                }
                return $energyData;
            });
    }

    /**
     * @param array $context
     * @return PromiseInterface
     * @throws Exception
     */
    private function getLatestKpi(array $context): PromiseInterface
    {
        $kpi = new KpiLatest();
        $this->asyncDataTransferTo($kpi);
        $deferred = new Deferred();
        $this->allowEnergyKpiUpdate ?
            $kpi->latest(
                (int)$context[self::CONTEXT_PARAM_LAST_UPDATE_TIME],
                $context[self::CONTEXT_PARAM_TEAM_ID]
            )->then(function (array $queryResultRows) use ($deferred) {
                $deferred->resolve($queryResultRows);
            }) :
            resolveOnFutureTick($deferred, [
                'ecology' => [],
                'shipping' => [],
                'energy' => []
            ]);
        return $deferred->promise()
            ->then(function (array $results) use ($context) {
                if (($_ENV['DEBUG_PERF_TIMING'] ?? null) !== null) {
                    wdo((microtime(true) - $context[self::CONTEXT_PARAM_NEW_TIME]) . ' elapsed after kpi<br />');
                }
                return $results;
            });
    }

    /**
     * @throws Exception
     */
    private function getLatestWarning(array $context): PromiseInterface
    {
        $warning = new WarningLatest();
        $this->asyncDataTransferTo($warning);
        return $warning->latest()
            ->then(function (Result $queryResult) use ($context) {
                if (($_ENV['DEBUG_PERF_TIMING'] ?? null) !== null) {
                    wdo((microtime(true) - $context[self::CONTEXT_PARAM_NEW_TIME]) . ' elapsed after warning<br />');
                }
                return $queryResult;
            });
    }

    /**
     * @throws Exception
     */
    private function getLatestObjective(array $context): PromiseInterface
    {
        $objective = new ObjectiveLatest();
        $this->asyncDataTransferTo($objective);
        return $objective->latest($context[self::CONTEXT_PARAM_LAST_UPDATE_TIME])
            ->then(function (Result $result) use ($context) {
                if (($_ENV['DEBUG_PERF_TIMING'] ?? null) !== null) {
                    wdo((microtime(true) - $context[self::CONTEXT_PARAM_NEW_TIME]) . ' elapsed after objective<br />');
                }
                return $result;
            });
    }
}
