<?php

namespace App\src\Repository\SessionAPI;

use App\Domain\API\v1\Simulation;
use App\Domain\Common\EntityEnums\EventLogSeverity;
use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Domain\Log\LogContainerInterface;
use App\Domain\Log\LogContainerTrait;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\SessionAPI\Simulation as SimulationEntity;
use App\Entity\SessionAPI\Watchdog;
use Exception;
use Symfony\Component\Uid\Uuid;

class WatchdogRepository extends LoggableEntityRepository implements LogContainerInterface
{
    use LogContainerTrait;

    public function removeUnresponsiveWatchdogs(): void
    {
        $watchdogs = $this->findBy(['status' => WatchdogStatus::UNRESPONSIVE]);
        foreach ($watchdogs as $watchdog) {
            $this->getEntityManager()->remove($watchdog);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * @param array<'CEL'|'MEL'|'SEL', mixed> $internalSims all internal simulations,
     *   the key being the name the value being the version string.
     * @throws Exception
     */
    public function registerSimulations(array $internalSims): void
    {
        $serverWatchdogRepo = ConnectionManager::getInstance()->getServerManagerEntityManager()
            ->getRepository(GameWatchdogServer::class);
        $serverWatchdogs = collect($serverWatchdogRepo->findAll())->keyBy(
            fn(GameWatchdogServer $s) => $s->getServerId()->toRfc4122()
        )->all();

        /** @var Watchdog[] $watchdogs */
        $watchdogs = $this->findAll();
        foreach ($watchdogs as $watchdog) {
            if (null !== $watchdog->getGameWatchdogServer()) {
                continue;
            }
            $message = 'no server assigned. Watchdog was removed.';
            $this->getEntityManager()->persist(Simulation::createEventLogForWatchdog(
                $message,
                EventLogSeverity::ERROR,
                $watchdog
            ));
            $this->log(
                sprintf('Watchdog %s: %s.', $watchdog->getServerId()->toRfc4122(), $message),
                self::LOG_LEVEL_WARNING
            );
            $this->getEntityManager()->remove($watchdog);
        }
        foreach ($serverWatchdogs as $serverWatchdog) {
            $this->upsertSessionWatchdog($serverWatchdog->getServerId());
        }

        // below is only for the internal watchdog
        if (empty($internalSims)) {
            $this->log(
                'No internal simulations found in game configuration. Skipping registration.',
                self::LOG_LEVEL_WARNING
            );
            return; // no configured simulations
        }
        $watchdog = $this->findOneBy(['serverId' => Watchdog::getInternalServerId()]);
        $simulations = collect($watchdog->getSimulations()->toArray())->keyBy(fn(SimulationEntity $s) => $s->getName())
            ->all();
        foreach ($internalSims as $simName => $simVersion) {
            if (array_key_exists($simName, $simulations)) {
                $this->log("Simulation {$simName} already registered, skipping.");
                continue;
            }
            $sim = new SimulationEntity();
            $sim->setName($simName);
            $sim->setVersion($simVersion);
            $sim->setWatchdog($watchdog);
            $this->getEntityManager()->persist($sim);
        }
        $this->getEntityManager()->flush();
    }

    private function upsertSessionWatchdog(Uuid $serverId): void
    {
        $watchdog = $this->findOneBy(['serverId' => $serverId]);
        $watchdog ??= new Watchdog();
        $watchdog
            ->setServerId($serverId)
            ->setDeletedAt(null)
            ->setToken(0) // this is temporary and will be updated later
            ->setStatus(WatchdogStatus::READY);
        $this->getEntityManager()->persist($watchdog);
        $this->getEntityManager()->flush();

        // update watchdog record with token using DQL
        $qb = $this->createQueryBuilder('w');
        $qb
            ->update()
            ->set('w.token', 'UUID_SHORT()')
            ->where($qb->expr()->eq('w.serverId', ':serverId'))
            ->setParameter('serverId', $serverId->toBinary())
            ->getQuery()
            ->execute();
    }
}
