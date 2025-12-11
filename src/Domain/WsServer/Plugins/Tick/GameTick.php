<?php

namespace App\Domain\WsServer\Plugins\Tick;

use App\Domain\API\v1\Game;
use App\Domain\API\v1\Plan;
use App\Domain\API\v1\Simulation;
use App\Domain\Common\Context;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Message\GameList\GameListCreationMessage;
use App\Message\Watchdog\Message\GameMonthChangedMessage;
use App\SilentFailException;
use App\Entity\SessionAPI\Watchdog;
use Drift\DBAL\Result;
use Exception;
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
                            ->then(function (?string $newState) use ($contextTryTickServer, $tickData) {
                                $this->getStopwatch()?->stop($contextTryTickServer?->getPath());
                                // only activate this after the Tick call has moved out of the client and into the
                                //   Watchdog
                                $contextUpdateGameDetails =
                                    $contextTryTickServer?->enter('updateGameDetailsAtServerManager');
                                $this->getStopwatch()?->start($contextUpdateGameDetails?->getPath());
                                return $this->updateGameDetailsAtServerManager(
                                    $tickData['state'] != $newState &&
                                    $newState == GameStateValue::END->value
                                )->then(function ($result) use ($contextUpdateGameDetails) {
                                    $this->getStopwatch()?->stop($contextUpdateGameDetails?->getPath());
                                    return $result;
                                });
                            });
                    }
                }
                $context = $context?->enter('updateGameDetailsAtServerManager');
                $this->getStopwatch()?->start($context?->getPath());
                // only activate this after the Tick call has moved out of the client and into the Watchdog
                return $this->updateGameDetailsAtServerManager(false)
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
                    'game_state as state'
                )
                ->from('game')
                ->setMaxResults(1)
        );
    }

    /**
     * @throws Exception
     */
    private function tryTickServer(array $tickData, bool $showDebug, ?Context $context): ?PromiseInterface
    {
        $state = $tickData['state'];

        $simulation = new Simulation();
        $this->asyncDataTransferTo($simulation);
        $simulation->getUnsynchronizedSimulations($tickData['month'])
            ->then(function (Result $result) {
                if ($result->fetchCount() == 0) { // all simulations are up-to-date
                    $this->getAsyncDatabase()->queryBySQL(
                        'UPDATE game SET game_transition_state = NULL, game_transition_month = NULL WHERE 1'
                    );
                }
                return null;
            });

        // no "tick" required for these state names
        if (in_array(
            $state,
            [GameStateValue::END->value, GameStateValue::PAUSE->value, GameStateValue::SETUP->value]
        )) {
            return null;
        }

        // check if we should postpone the "tick"
        $diff = microtime(true) - $tickData['lastupdate'];
        $secondsPerMonth = ($state == GameStateValue::SIMULATION->value ||
            $state == GameStateValue::FASTFORWARD->value) ? 0.2 :
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

        $simulation = new Simulation();
        $this->asyncDataTransferTo($simulation);
        $context = $context?->enter('getUnsynchronizedSimulations');
        $this->getStopwatch()?->start($context?->getPath());
        return $simulation->getUnsynchronizedSimulations($tickData['month'])
            ->then(function (Result $result) use ($showDebug, $context) {
                $this->getStopwatch()?->stop($context?->getPath());
                if ($result->fetchCount() == 0) { // all simulations are up-to-date
                    return $this->serverTickInternal($showDebug, $context)
                        ->otherwise(function (SilentFailException $e) {
                            // Handle the rejection, and don't propagate. This is like catch without a rethrow
                            return null;
                        });
                }
                if ($showDebug) {
                    wdo('Waiting for simulations to update.', OutputInterface::VERBOSITY_VERY_VERBOSE);
                }
                return null;
            });
    }

    /**
     * @throws Exception
     */
    private function updateGameDetailsAtServerManager(bool $gameJustEnded): PromiseInterface
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
            })
            ->then(function (/* Result $result */) use ($gameJustEnded) {
                if ($gameJustEnded) {
                    $this->signalDemoSessionRecreate();
                }
            });
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    private function signalDemoSessionRecreate(): void
    {
        $connection = ConnectionManager::getInstance()->getCachedAsyncServerManagerDbConnection(Loop::get());
        $qb = $connection->createQueryBuilder();
        $connection->query(
            $qb->select('demo_session')->from('game_list')
                ->where($qb->expr()->eq('id', $this->getGameSessionId()))
        )->done(function (Result $result) {
            $row = $result->fetchFirstRow() ?? [];
            if (!isset($row['demo_session']) || $row['demo_session'] == 0) {
                return;
            }
            SymfonyToLegacyHelper::getInstance()->getMessageBus()->dispatch(
                new GameListCreationMessage($this->getGameSessionId(), true)
            );
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
                    'game_transition_state as transition_state',
                    'game_state as state',
                    'game_transition_month as transition_month',
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
                        ->then(function (?string $newState) use ($context) {
                            $this->getStopwatch()?->stop($context?->getPath());
                            return $newState;
                        });
                })
                ->then(function (?string $newState) use ($currentMonth, $contextUpdateLayerState) {
                    $context = $contextUpdateLayerState?->enter('getWatchdogs');
                    $this->getStopwatch()?->start($context?->getPath());
                    $simulation = new Simulation();
                    $this->asyncDataTransferTo($simulation);
                    // no return! so we don't wait for the response
                    $simulation->getWatchdogs()->then(
                        /**
                         * @throws Exception
                         * @var Watchdog[] $watchdogs
                         */
                        function (array $watchdogs) use ($currentMonth, $context) {
                            $this->getStopwatch()?->stop($context?->getPath());
                            foreach ($watchdogs as $watchdog) {
                                $message = new GameMonthChangedMessage();
                                $message
                                    ->setGameSessionId($this->getGameSessionId())
                                    ->setWatchdogId($watchdog->getId())
                                    ->setMonth($currentMonth);
                                SymfonyToLegacyHelper::getInstance()->getMessageBus()->dispatch($message);
                            }
                        }
                    );
                    return $newState;
                })
                ->then(function (?string $newState) use ($tick) {
                    if (($tick['month'] % $tick['autosave_interval_months']) == 0) {
                        $game = new Game();
                        $this->asyncDataTransferTo($game);
                        $game->AutoSaveDatabase(); // this is async by default
                    }
                    return $newState;
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
            ->set('game_transition_month', (string)($tick['transition_month'] ?? $tick['month']))
            ->set('game_currentmonth', (string)$currentMonth)
            ->set('game_planning_monthsdone', (string)$monthsDone);

        if ($currentMonth >= ($tick['era_time'] * 4)) { //Hardcoded to 4 eras as designed.
            //Entire game is done.
            return $this->getAsyncDatabase()->query(
                $qb
                    ->set('game_transition_state', $tick['transition_state'] ?? $tick['state'])
                    ->set('game_state', $qb->createPositionalParameter(GameStateValue::END->value))
            )
            ->then(function (/*Result $result*/) use ($currentMonth) {
                $simulation = new Simulation();
                $this->asyncDataTransferTo($simulation);
                return $simulation->changeWatchdogState(GameStateValue::END, $currentMonth);
            });
        } elseif (($state == GameStateValue::PLAY->value || $state == GameStateValue::FASTFORWARD->value)
            && $monthsDone >= $tick['era_gametime'] &&
            $tick['era_gametime'] < $tick['era_time']
        ) {
            //planning phase is complete, move to the simulation phase
            return $this->getAsyncDatabase()->query(
                $qb
                    ->set('game_planning_monthsdone', '0')
                    ->set('game_transition_state', $tick['transition_state'] ?? $tick['state'])
                    ->set('game_state', $qb->createPositionalParameter(GameStateValue::SIMULATION->value))
            )
            ->then(function (/*Result $result*/) use ($currentMonth) {
                $simulation = new Simulation();
                $this->asyncDataTransferTo($simulation);
                return $simulation->changeWatchdogState(GameStateValue::SIMULATION, $currentMonth);
            });
        } elseif (($state == GameStateValue::SIMULATION->value &&
                $monthsDone >= $tick['era_time'] - $tick['era_gametime']
            ) ||
            $monthsDone >= $tick['era_time']) {
            //simulation is done, reset everything to start a new play phase
            $era = floor($currentMonth / $tick['era_time']);
            $era_realtime = explode(',', $tick['planning_era_realtime']);
            return $this->getAsyncDatabase()->query(
                $qb
                    ->set('game_planning_monthsdone', '0')
                    ->set('game_transition_state', $tick['transition_state'] ?? $tick['state'])
                    ->set('game_state', $qb->createPositionalParameter(GameStateValue::PLAY->value))
                    ->set('game_planning_realtime', $era_realtime[$era])
            )
            ->then(function (Result $result) use ($currentMonth) {
                $simulation = new Simulation();
                $this->asyncDataTransferTo($simulation);
                return $simulation->changeWatchdogState(GameStateValue::PLAY, $currentMonth);
            });
        } else {
            return $this->getAsyncDatabase()->query($qb)
                ->then(function (/* Result $result */) use ($state) {
                    return $state;
                });
        }
    }
}
