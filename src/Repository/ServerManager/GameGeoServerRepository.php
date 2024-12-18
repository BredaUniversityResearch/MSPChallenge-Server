<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameGeoServer;
use Doctrine\ORM\EntityRepository;

class GameGeoServerRepository extends EntityRepository
{
    public function save(GameGeoServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameGeoServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
