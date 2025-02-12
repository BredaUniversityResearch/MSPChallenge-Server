<?php

namespace App\Domain\WsServer\Plugins\Tick;

use App\Domain\API\v1\Game;
use App\Domain\API\v1\Plan;
use App\Domain\Common\Context;
use App\Domain\Common\MSPBrowserFactory;
use App\Domain\Services\ConnectionManager;
use App\SilentFailException;
use Drift\DBAL\Result;
use Exception;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function App\query;

class GameTick extends TickBase
{
    /**
     * Tick the game server, updating the plans if required
     *
     * @param bool $showDebug
     * @param Context|null $context
     * @return PromiseInterface
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function tick(bool $showDebug = false, ?Context $context = null): PromiseInterface
    {
        // fetch game information, incl. state name
        $context = $context?->enter('GameTick::tick');
        $this->getStopwatch()?->start($context?->getPath());
        return $this->getTickData()
            ->then(function (Result $result) use ($showDebug, $context) {
                $this->getStopwatch()?->stop($context?->getPath());
                if (null !== $tickData = $result->fetchFirstRow()) {
                    $contextTryTickServer = $context?->enter('tryTickServer');
                    $promise = $this->tryTickServer($tickData, $showDebug, $contextTryTickServer);
                    if (null !== $promise) {
                        $this->getStopwatch()?->start($contextTryTickServer?->getPath());
                        return $promise
                            ->then(function () use ($contextTryTickServer) {
                                $this->getStopwatch()?->stop($contextTryTickServer?->getPath());
                                // only activate this after the Tick call has moved out of the client and into the
                                //   Watchdog
                                $contextUpdateGameDetails =
                                    $contextTryTickServer?->enter('updateGameDetailsAtServerManager');
                                $this->getStopwatch()?->start($contextUpdateGameDetails?->getPath());
                                return $this->updateGameDetailsAtServerManager()
                                    ->then(function ($result) use ($contextUpdateGameDetails) {
                                        $this->getStopwatch()?->stop($contextUpdateGameDetails?->getPath());
                                        return $result;
                                    });
                            });
                    }
                }
                $context = $context?->enter('updateGameDetailsAtServerManager');
                $this->getStopwatch()?->start($context?->getPath());
                // only activate this after the Tick call has moved out of the client and into the Watchdog
                return $this->updateGameDetailsAtServerManager()
                    ->then(function ($result) use ($context) {
                        $this->getStopwatch()?->stop($context?->getPath());
                        return $result;
                    });
            });
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    private function getTickData(): PromiseInterface
    {
        return $this->getAsyncDatabase()->query(
            $this->getAsyncDatabase()->createQueryBuilder()
                ->select(
                    'game_lastupdate as lastupdate',
                    'game_currentmonth as month',
                    'game_planning_gametime as era_gametime',
                    'game_planning_realtime as era_realtime',
                    'game_mel_lastmonth as mel_lastmonth',
                    'game_cel_lastmonth as cel_lastmonth',
                    'game_sel_lastmonth as sel_lastmonth',
                    'game_state as state'
                )
                ->from('game')
                ->setMaxResults(1)
        );
    }

    /**
     * @throws Exception
     */
    private function tryTickServer(array $tickData, bool $showDebug, Context $context): ?PromiseInterface
    {
        $state = $tickData['state'];
        // no "tick" required for these state names
        if (in_array($state, ['END', 'PAUSE', 'SETUP'])) {
            return null;
        }

        // check if we should postpone the "tick"
        $diff = microtime(true) - $tickData['lastupdate'];
        $secondsPerMonth = ($state == 'SIMULATION' || $state == 'FASTFORWARD') ? 0.2 :
            ($tickData['era_realtime'] / $tickData['era_gametime']);
        if ($diff <= $secondsPerMonth) {
            if ($showDebug) {
                wdo(
                    "Waiting for update time " . ($secondsPerMonth - $diff) . " seconds remaining",
                    OutputInterface::VERBOSITY_VERY_VERBOSE
                );
            }
            return null;
        }

        // let's do the "tick" which updates server time and month
        if ($showDebug) {
            wdo("Trying to tick the server", OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        $game = new Game();
        $this->asyncDataTransferTo($game);
        if (!$game->areSimulationsUpToDate($tickData)) {
            if ($showDebug) {
                wdo('Waiting for simulations to update.', OutputInterface::VERBOSITY_VERY_VERBOSE);
            }
            return null;
        }

        return $this->serverTickInternal($showDebug, $context)
            ->otherwise(function (SilentFailException $e) {
                // Handle the rejection, and don't propagate. This is like catch without a rethrow
                return null;
            });
    }

    /**
     * @throws Exception
     */
    private function updateGameDetailsAtServerManager(): PromiseInterface
    {
        $game = new Game();
        $this->asyncDataTransferTo($game);
        return $game->getGameDetails()
            ->then(function (array $postValues) use ($game) {
                $connection = ConnectionManager::getInstance()->getCachedAsyncServerManagerDbConnection(Loop::get());
                $qb = $connection->createQueryBuilder();
                $qb->update('game_list');
                foreach ($postValues as $column => $value) {
                    $qb->set($column, $qb->createPositionalParameter($value));
                }
                $qb->where($qb->expr()->eq('id', $game->getGameSessionId()));
                return $connection->query($qb);
            });
    }

    /**
     * @throws Exception
     */
    private function serverTickInternal(bool $showDebug, ?Context $context): Promise
    {
        $context = $context?->enter('serverTickInternal');
        $this->getStopwatch()?->start($context?->getPath());
        if ($showDebug) {
            wdo('Ticking server.', OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
        return query(
            $this->getAsyncDatabase(),
            $this->getAsyncDatabase()->createQueryBuilder()
                ->select(
                    'game_state as state',
                    'game_currentmonth as month',
                    'game_planning_gametime as era_gametime',
                    'game_planning_realtime as era_realtime',
                    'game_planning_era_realtime as planning_era_realtime',
                    'game_planning_monthsdone as era_monthsdone',
                    'game_eratime as era_time',
                    'game_autosave_month_interval as autosave_interval_months'
                )
                ->from('game')
                ->setMaxResults(1)
        )
        ->then(function (Result $result) use ($context) {
            $this->getStopwatch()?->stop($context?->getPath());
            $tick = $result->fetchFirstRow();
            $state = $tick['state'];

            $monthsDone = $tick['era_monthsdone'] + 1;
            $currentMonth = $tick['month'] + 1;

            //update all the plans which ticks the server.
            $plan = new Plan();
            $this->asyncDataTransferTo($plan);
            $contextUpdateLayerState = $context?->enter('updateLayerState');
            $this->getStopwatch()?->start($contextUpdateLayerState?->getPath());
            return $plan->updateLayerState($currentMonth)
                ->then(function (/* array $results */) use (
                    $currentMonth,
                    $monthsDone,
                    $state,
                    $tick,
                    $contextUpdateLayerState
                ) {
                    $this->getStopwatch()?->stop($contextUpdateLayerState?->getPath());
                    $context = $contextUpdateLayerState?->enter('advanceGameTime');
                    $this->getStopwatch()?->start($context?->getPath());
                    return $this->advanceGameTime($currentMonth, $monthsDone, $state, $tick)
                        ->then(function ($result) use ($context) {
                            $this->getStopwatch()?->stop($context?->getPath());
                            return $result;
                        });
                })
                ->then(function () use ($currentMonth, $contextUpdateLayerState) {
                    $contextGetWatchdogToken = $contextUpdateLayerState?->enter('getWatchdogSessionUniqueToken');
                    $this->getStopwatch()?->start($contextGetWatchdogToken?->getPath());
                    // no return! so we don't wait for the response
                    $this->getWatchdogSessionUniqueToken()->then(
                        function (string $watchdogSessionUniqueToken) use ($currentMonth, $contextGetWatchdogToken) {
                            $this->getStopwatch()?->stop($contextGetWatchdogToken?->getPath());
                            // note(MH): GetWatchdogAddress is not async, but it is cached once it
                            //   has been retrieved once, so that's "fine"
                            $contextWatchdogSetMonth = $contextGetWatchdogToken?->enter('watchdogSetMonth');
                            $this->getStopwatch()?->start($contextWatchdogSetMonth?->getPath());
                            $url = $this->GetWatchdogAddress()."/Watchdog/SetMonth";
                            MSPBrowserFactory::create($url)->post(
                                $url,
                                [
                                    'Content-Type' => 'application/x-www-form-urlencoded'
                                ],
                                http_build_query([
                                    'game_session_token' => $watchdogSessionUniqueToken,
                                    'month' => $currentMonth
                                ])
                            )
                            ->then(function (ResponseInterface $response) use ($url, $contextWatchdogSetMonth) {
                                $this->getStopwatch()?->stop($contextWatchdogSetMonth?->getPath());
                                $contextLogWatchdogResponse = $contextWatchdogSetMonth?->enter('logWatchdogResponse');
                                $this->getStopwatch()?->start($contextLogWatchdogResponse?->getPath());
                                return $this->logWatchdogResponse($url, $response)
                                    ->then(function ($result) use ($contextLogWatchdogResponse) {
                                        $this->getStopwatch()?->stop($contextLogWatchdogResponse?->getPath());
                                        return $result;
                                    });
                            });
                        }
                    );
                })
                ->then(function () use ($tick) {
                    if (($tick['month'] % $tick['autosave_interval_months']) == 0) {
                        $game = new Game();
                        $this->asyncDataTransferTo($game);
                        $game->AutoSaveDatabase(); // this is async by default
                    }
                });
        });
    }

    /**
     * Updates time to the next month
     * @throws Exception
     */
    private function advanceGameTime(int $currentMonth, int $monthsDone, string $state, array $tick): PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();

        // set the default update query and its values
        $qb
            ->update('game')
            ->set('game_lastupdate', 'UNIX_TIMESTAMP(NOW(4))')
            ->set('game_currentmonth', (string)$currentMonth)
            ->set('game_planning_monthsdone', (string)$monthsDone);

        if ($currentMonth >= ($tick['era_time'] * 4)) { //Hardcoded to 4 eras as designed.
            //Entire game is done.
            return $this->getAsyncDatabase()->query(
                $qb
                    ->set('game_state', $qb->createPositionalParameter('END'))
            )
            ->then(function (/*Result $result*/) {
                $game = new Game();
                $this->asyncDataTransferTo($game);
                return $game->changeWatchdogState('END');
            });
        } elseif (($state == "PLAY" || $state == "FASTFORWARD") && $monthsDone >= $tick['era_gametime'] &&
            $tick['era_gametime'] < $tick['era_time']) {
            //planning phase is complete, move to the simulation phase
            return $this->getAsyncDatabase()->query(
                $qb
                    ->set('game_planning_monthsdone', '0')
                    ->set('game_state', $qb->createPositionalParameter('SIMULATION'))
            )
            ->then(function (/*Result $result*/) {
                $game = new Game();
                $this->asyncDataTransferTo($game);
                return $game->changeWatchdogState('SIMULATION');
            });
        } elseif (($state == "SIMULATION" && $monthsDone >= $tick['era_time'] - $tick['era_gametime']) ||
            $monthsDone >= $tick['era_time']) {
            //simulation is done, reset everything to start a new play phase
            $era = floor($currentMonth / $tick['era_time']);
            $era_realtime = explode(',', $tick['planning_era_realtime']);
            return $this->getAsyncDatabase()->query(
                $qb
                    ->set('game_planning_monthsdone', '0')
                    ->set('game_state', $qb->createPositionalParameter('PLAY'))
                    ->set('game_planning_realtime', $era_realtime[$era])
            )
            ->then(function (Result $result) {
                $game = new Game();
                $this->asyncDataTransferTo($game);
                return $game->changeWatchdogState('PLAY');
            });
        } else {
            return $this->getAsyncDatabase()->query($qb);
        }
    }
}
