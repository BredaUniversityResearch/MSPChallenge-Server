<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\API\v1\Game;
use App\Domain\Common\CommonBase;
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
     * Gets the latest plans & messages from the server
     *
     * @param int $teamId
     * @param float $lastUpdateTime
     * @param int $user
     * @param bool $showDebug
     * @return PromiseInterface
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Latest(int $teamId, float $lastUpdateTime, int $user, bool $showDebug = false): PromiseInterface
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
            $newTime = microtime(true);
            $data = array();
            $data['prev_update_time'] = $lastUpdateTime;
            return $this->latestLevel2(
                $tick,
                $lastUpdateTime,
                $newTime,
                $data
            )
            ->then(function (array $planData) use (
                $teamId,
                $lastUpdateTime,
                $user,
                $newTime,
                &$data
            ) {
                return $this->latestLevel3(
                    $planData,
                    $lastUpdateTime,
                    $newTime,
                    $data
                )
                ->then(function (array $layersContainer) use (
                    $teamId,
                    $lastUpdateTime,
                    $user,
                    $newTime,
                    &$data
                ) {
                    return $this->latestLevel4(
                        $layersContainer,
                        $teamId,
                        $lastUpdateTime,
                        $newTime,
                        $data
                    )
                    ->then(function (array $result) use (
                        $teamId,
                        $lastUpdateTime,
                        $user,
                        $newTime,
                        &$data
                    ) {
                        return $this->latestLevel5(
                            $result,
                            $lastUpdateTime,
                            $newTime,
                            $data
                        )
                        ->then(function (Result $result) use (
                            $teamId,
                            $lastUpdateTime,
                            $user,
                            $newTime,
                            &$data
                        ) {
                            return $this->latestLevel6( // energy
                                $result,
                                $newTime,
                                $data
                            )
                            ->then(function (array $results) use (
                                $teamId,
                                $lastUpdateTime,
                                $user,
                                $newTime,
                                &$data
                            ) {
                                return $this->latestLevel7(
                                    $results,
                                    $teamId,
                                    $lastUpdateTime,
                                    $newTime,
                                    $data
                                )
                                ->then(function (array $results) use (
                                    $lastUpdateTime,
                                    $user,
                                    $newTime,
                                    &$data
                                ) {
                                    return $this->latestLevel8(
                                        $results,
                                        $lastUpdateTime,
                                        $newTime,
                                        $data
                                    )
                                    ->then(function (Result $queryResult) use (
                                        $lastUpdateTime,
                                        $user,
                                        $newTime,
                                        &$data
                                    ) {
                                        return $this->latestLevel9(
                                            $queryResult,
                                            $lastUpdateTime,
                                            $newTime,
                                            $data
                                        )
                                        ->then(function (Result $result) use (
                                            $user,
                                            $newTime,
                                            &$data
                                        ) {
                                            return $this->latestLevel10($result, $user, $newTime, $data)
                                                ->then(function (/*?Result $result */) use ($data) {
                                                    return $data;
                                                });
                                        });
                                    });
                                });
                            });
                        });
                    });
                });
            });
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
     * @throws Exception
     */
    private function latestLevel2(array $tick, float $lastUpdateTime, float $newTime, array &$data): PromiseInterface
    {
        $data['tick'] = $tick;
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            wdo((microtime(true) - $newTime) . ' elapsed after tick');
        }
        $plan = new PlanLatest();
        $this->asyncDataTransferTo($plan);
        return $plan->latest((int)$lastUpdateTime);
    }

    /**
     * @throws Exception
     */
    private function latestLevel3(
        array $planData,
        float $lastUpdateTime,
        float $newTime,
        array &$data
    ): PromiseInterface {
        $data['plan'] = $planData;
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            wdo((microtime(true) - $newTime) . ' elapsed after plan');
        }

        $layer = new LayerLatest();
        $this->asyncDataTransferTo($layer);
        $toPromiseFunctions = [];
        foreach ($data['plan'] as $p) {
            $toPromiseFunctions[] = tpf(function () use ($layer, $p, $lastUpdateTime) {
                return $layer->latest($p['layers'], $lastUpdateTime, $p['id']);
            });
        }
        return parallel(
            $toPromiseFunctions
        );
    }

    /**
     * @throws Exception
     */
    private function latestLevel4(
        array $layersContainer,
        int $teamId,
        float $lastUpdateTime,
        float $newTime,
        array &$data
    ): PromiseInterface {
        foreach ($data['plan'] as $key => &$p) {
            //only send the geometry when it's required
            $p['layers'] = $layersContainer[$key];
            if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
                wdo((microtime(true) - $newTime) . ' elapsed after layers<br />');
            }

            if ((
                $p['state'] == "DESIGN" && $p['previousstate'] == "CONSULTATION" &&
                $p['country'] != $teamId
            )) {
                $p['active'] = 0;
            }
        }
        unset($p);

        $plan = new PlanLatest();
        $this->asyncDataTransferTo($plan);
        return $plan->getMessages(
            $lastUpdateTime
        );
    }

    /**
     * @throws Exception
     */
    private function latestLevel5(
        array $queryResultRows,
        float $lastUpdateTime,
        float $newTime,
        array &$data
    ): PromiseInterface {
        $data['planmessages'] = $queryResultRows;
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            wdo((microtime(true) - $newTime) . ' elapsed after plan messages');
        }

        //return any raster layers that need to be updated
        $layer = new LayerLatest();
        $this->asyncDataTransferTo($layer);
        return $layer->latestRaster(
            $lastUpdateTime
        );
    }

    /**
     * @throws Exception
     */
    private function latestLevel6(
        Result $queryResult,
        float $newTime,
        array &$data
    ): PromiseInterface {
        $data['raster'] = $queryResult->fetchAllRows();
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            wdo((microtime(true) - $newTime) . ' elapsed after raster<br />');
        }

        $energy = new EnergyLatest();
        $this->asyncDataTransferTo($energy);
        $deferred = new Deferred();
        $energy->fetchAll($this->allowEnergyKpiUpdate)->then(function (array $queryResults) use ($deferred) {
            $energyData['connections'] = $queryResults[0]->fetchAllRows();
            $energyData['output'] = $queryResults[1]->fetchAllRows();
            $deferred->resolve($energyData);
        });
        return $deferred->promise();
    }

    /**
     * @param array $energyData
     * @param int $teamId
     * @param float $newTime
     * @param array $data
     * @return PromiseInterface
     * @throws Exception
     */
    private function latestLevel7(
        array $energyData,
        int $teamId,
        float $lastUpdateTime,
        float $newTime,
        array &$data
    ): PromiseInterface {
        $data['policy_updates'][] = array_merge([
            'policy_type' => 'energy',
        ], $energyData);
        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            wdo((microtime(true) - $newTime) . ' elapsed after energy');
        }

        $kpi = new KpiLatest();
        $this->asyncDataTransferTo($kpi);
        $deferred = new Deferred();
        $this->allowEnergyKpiUpdate ?
            $kpi->latest((int)$lastUpdateTime, $teamId)->then(function (array $queryResultRows) use ($deferred) {
                $deferred->resolve($queryResultRows);
            }) :
            resolveOnFutureTick($deferred, [
                'ecology' => [],
                'shipping' => [],
                'energy' => []
            ]);
        return $deferred->promise();
    }

    /**
     * @throws Exception
     */
    private function latestLevel8(
        array $queryResultRows,
        float $lastUpdateTime,
        float $newTime,
        array &$data
    ): PromiseInterface {
        $data['simulation_updates'][0] = [
            'simulation_type' => 'CEL',
            'kpi' => $queryResultRows['energy']
        ];
        $data['simulation_updates'][1] = [
            'simulation_type' => 'MEL',
            'kpi' => $queryResultRows['ecology']
        ];
        $data['simulation_updates'][2] = [
            'simulation_type' => 'SEL',
            'kpi' => $queryResultRows['shipping']
        ];

        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            wdo((microtime(true) - $newTime) . ' elapsed after kpi<br />');
        }

        $warning = new WarningLatest();
        $this->asyncDataTransferTo($warning);
        return $warning->latest();
    }

    /**
     * @throws Exception
     */
    private function latestLevel9(
        Result $queryResult,
        float $lastUpdateTime,
        float $newTime,
        array &$data
    ): PromiseInterface {
        $data['simulation_updates'][2]['shipping_issues'] = $queryResult->fetchAllRows();

        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            wdo((microtime(true) - $newTime) . ' elapsed after warning<br />');
        }

        $objective = new ObjectiveLatest();
        $this->asyncDataTransferTo($objective);
        return $objective->latest(
            $lastUpdateTime
        );
    }

    /**
     * @throws Exception
     */
    private function latestLevel10(
        Result $result,
        int $user,
        float $newTime,
        array &$data
    ): PromiseInterface {
        $data['objectives'] = $result->fetchAllRows();

        if (defined('DEBUG_PREF_TIMING') && DEBUG_PREF_TIMING === true) {
            wdo((microtime(true) - $newTime) . ' elapsed after objective<br />');
        }

        //Add a slight fudge of 1ms to the update times to avoid rounding issues.
        $data['update_time'] = $newTime - 0.001;

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
            return resolveOnFutureTick(new Deferred(), '')->promise();
        }

        return $this->getAsyncDatabase()->update(
            'user',
            [
                'user_id' => $user
            ],
            [
                'user_lastupdate' => $newTime
            ]
        );
    }
}
