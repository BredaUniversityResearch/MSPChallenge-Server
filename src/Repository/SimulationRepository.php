<?php

namespace App\Repository;

use App\Domain\Common\InternalSimulationName;
use App\Entity\Watchdog;
use Doctrine\ORM\EntityRepository;

class SimulationRepository extends EntityRepository
{
    /**
     * @throws \Exception
     */
    public function notifyUpdateFinished(InternalSimulationName $simName, int $month): void
    {
        $sim = $this->findOneBy([
            'watchdog' => $this->getEntityManager()->getRepository(Watchdog::class)->findOneBy([
                'serverId' => Watchdog::getInternalServerId()->toBinary()
            ]),
            'name' => $simName->value
        ]);
        $sim->setLastMonth($month);
        $this->getEntityManager()->persist($sim);
        $this->getEntityManager()->flush();
    }
}
