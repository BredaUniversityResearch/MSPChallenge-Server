<?php

namespace App\Repository;

use App\Domain\Common\InternalSimulationName;
use App\Domain\Services\ConnectionManager;
use App\Entity\Watchdog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Shivas\VersioningBundle\Provider\VersionProvider;
use Symfony\Component\Uid\Uuid;

class SimulationRepository extends EntityRepository
{
    /**
     * @throws Exception
     */
    public function notifyMonthSimulationFinished(Uuid $watchdogServerId, string $simName, int $month): void
    {
        $sim = $this->findOneBy([
            'watchdog' => $this->getEntityManager()->getRepository(Watchdog::class)->findOneBy([
                'serverId' => $watchdogServerId
            ]),
            'name' => $simName
        ]);
        if (!$sim) {
            throw new EntityNotFoundException(
                sprintf("Could not find simulation %s for server %s", $simName, $watchdogServerId->toRfc4122())
            );
        }
        $sim->setLastMonth($month);
        $this->getEntityManager()->persist($sim);
        $this->getEntityManager()->flush();
    }

    /**
     * @throws Exception
     */
    public function notifyMonthFinishedForInternal(InternalSimulationName $simName, int $month): void
    {
        $this->notifyMonthSimulationFinished(Watchdog::getInternalServerId(), $simName->value, $month);
    }
}
