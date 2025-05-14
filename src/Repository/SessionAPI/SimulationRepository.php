<?php

namespace App\src\Repository\SessionAPI;

use App\Domain\Common\InternalSimulationName;
use App\src\Entity\SessionAPI\Watchdog;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\EntityRepository;
use Exception;
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
