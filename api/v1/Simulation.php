<?php

namespace App\Domain\API\v1;

use App\Domain\Common\EntityEnums\EventLogSeverity;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\EventLog;
use App\Entity\Simulation as SimulationEntity;
use App\Entity\Watchdog;
use App\Message\Watchdog\GameStateChangedMessage;
use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use Symfony\Component\Uid\Uuid;

class Simulation extends Base
{
    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function getSimulations(
        ?WatchdogStatus $statusFilter = null,
        ?int            $lastMonthFilter = null,
        ?float          $afterUpdateTimestamp = null,
        bool            $archived = false
    ): PromiseInterface {
        $expr = $this->getAsyncDatabase()->createQueryBuilder()->expr();
        $andExpressions = ['1=1'];
        $parameters = [];
        if ($statusFilter !== null) {
            $andExpressions[] = $expr->eq('w.status', '?');
            $parameters[] = $statusFilter->value;
        }
        if ($lastMonthFilter !== null) {
            $andExpressions[] = $expr->eq('s.last_month', $lastMonthFilter);
        }
        if ($afterUpdateTimestamp !== null) {
            $andExpressions[] = $expr->gt('UNIX_TIMESTAMP(s.updated_at)', '?');
            $parameters[] = $afterUpdateTimestamp;
        }
        return $this->findByWhere(
            $expr->and(...$andExpressions),
            $parameters,
            $archived
        );
    }

    /**
     * @description find simulations that are not yet up-to-date to the specified month
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function getUnsynchronizedSimulations(int $month): PromiseInterface
    {
        $expr = $this->getAsyncDatabase()->createQueryBuilder()->expr();
        return $this->findByWhere(
            // so the simulation is not yet up-to-date to the specified month
            $expr->and($expr->lt('s.last_month', $month))
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function findByWhere(
        CompositeExpression $where,
        array $parameters = [],
        bool $archived = false
    ) : PromiseInterface {
        $qb = $this->getAsyncDatabase()->createQueryBuilder()
            ->select('s.*')
            ->from('simulation', 's');
        if (!empty($parameters)) {
            $qb->setParameters($parameters);
        }
        $qb
            ->innerJoin('s', 'watchdog', 'w', 's.watchdog_id = w.id')
            ->andWhere($qb->expr()->eq('w.archived', $archived ? 1 : 0))
            ->andWhere($where);
        return $this->getAsyncDatabase()->query($qb);
    }

    private function createWatchdogEntityFromAssociative(array $watchdog): Watchdog
    {
        $w = new Watchdog();
        $w
            ->setId($watchdog['w_id'])
            ->setServerId(Uuid::fromBinary($watchdog['server_id']))
            ->setStatus(WatchdogStatus::from($watchdog['status']))
            ->setToken($watchdog['token'])
            ->setCreatedAt(new DateTime($watchdog['created_at']))
            ->setDeletedAt(new DateTime($watchdog['deleted_at']))
            ->setUpdatedAt(new DateTime($watchdog['updated_at']));
        return $w;
    }

    /**
     * @throws DateMalformedStringException
     */
    private function createSimulationEntityFromAssociative(array $sim): SimulationEntity
    {
        $s = new SimulationEntity();
        $s
            ->setName($sim['name'])
            ->setVersion($sim['version'])
            ->setLastMonth($sim['last_month'])
            ->setCreatedAt(new DateTime($sim['s_created_at']))
            ->setUpdatedAt(new DateTime($sim['s_updated_at']));
        return $s;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function getWatchdogs(): PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder()
            ->select(
                'w.*',
                's.*',
                // aliases for the columns, to be able to distinguish between the two tables
                'w.id as w_id',
                's.created_at as s_created_at',
                's.updated_at as s_updated_at'
            )
            ->from('watchdog', 'w')
            ->leftJoin('w', 'simulation', 's', 's.watchdog_id = w.id')
            ->where('w.deleted_at IS NULL'); // only active simulations
        return $this->getAsyncDatabase()->query($qb)->then(function (Result $result) {
            $sims = ($result->fetchAllRows() ?? []) ?: [];
            $watchdogs = [];
            foreach ($sims as $sim) {
                $watchdogs[$sim['w_id']] ??= $this->createWatchdogEntityFromAssociative($sim);
                if ($sim['name'] === null) {
                    continue;
                }
                $simEntity = $this->createSimulationEntityFromAssociative($sim);
                // this will also assign the watchdog to the simulation
                $watchdogs[$sim['w_id']]->getSimulations()->add($simEntity);
            }
            return $watchdogs;
        });
    }

    /**
     * @throws Exception
     */
    public function changeWatchdogState(string $newWatchdogGameState, ?int $currentMonth = null): PromiseInterface
    {
        return $this->getWatchdogs()->then(function (array $watchdogs) use (
            $newWatchdogGameState,
            $currentMonth
        ) {
            $game = new Game();
            $this->asyncDataTransferTo($game);
            $currentMonth ??= $game->GetCurrentMonthAsId(); // only query if not given already :)
            /** @var Watchdog $watchdog */
            foreach ($watchdogs as $watchdog) {
                $url = $watchdog->getGameWatchdogServer()?->createUrl();
                // this is used for the session creation log for instance
                $this->logWatchdogEvent(
                    $watchdog,
                    $url != null ?
                        sprintf('Requesting state change using url: %s', $url) :
                        new Exception('no server assigned. Watchdog will be removed')
                );
                $message = new GameStateChangedMessage();
                $message
                    ->setGameSessionId($this->getGameSessionId())
                    ->setWatchdog($watchdog)
                    ->setGameState(new GameStateValue($newWatchdogGameState))
                    ->setMonth($currentMonth);
                SymfonyToLegacyHelper::getInstance()->getMessageBus()->dispatch($message);
            }
        });
    }

    /**
     * @throws Exception
     */
    private function logWatchdogEvent(Watchdog $watchdog, string|Exception $message): void
    {
        $this->log(sprintf('Watchdog %s: %s', $watchdog->getServerId()->toRfc4122(), $message));
        if (!($message instanceof Exception)) {
            return;
        }
        // log exceptions to database
        $eventLog = self::createEventLogForWatchdog(
            $message->getMessage(),
            EventLogSeverity::ERROR,
            $watchdog,
            $message->getTraceAsString()
        );
        $this->getLogger()->postEvent($eventLog);
    }

    public static function createEventLogForWatchdog(
        string $message,
        EventLogSeverity $severity,
        ?Watchdog $w = null,
        ?string $stackTrace = null
    ): EventLog {
        $eventLog = new EventLog();
        $eventLog
            ->setSource(self::class)
            ->setMessage($message)
            ->setSeverity($severity)
            ->setStackTrace($stackTrace);
        if (null !== $w) {
            $eventLog
                ->setReferenceObject(Watchdog::class)
                ->setReferenceId($w->getId());
        }
        return $eventLog;
    }
}
