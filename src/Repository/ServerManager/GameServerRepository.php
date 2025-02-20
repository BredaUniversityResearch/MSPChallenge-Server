<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameServer;
use Doctrine\ORM\EntityRepository;

class GameServerRepository extends EntityRepository
{
    public function save(GameServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
