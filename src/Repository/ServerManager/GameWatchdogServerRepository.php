<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameWatchdogServer;
use Doctrine\ORM\EntityRepository;

class GameWatchdogServerRepository extends EntityRepository
{
    public function save(GameWatchdogServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameWatchdogServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
