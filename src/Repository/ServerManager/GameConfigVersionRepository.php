<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameConfigVersion;
use Doctrine\ORM\EntityRepository;

class GameConfigVersionRepository extends EntityRepository
{
    public function save(GameConfigVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameConfigVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
