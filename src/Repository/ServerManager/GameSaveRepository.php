<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameSave;
use Doctrine\ORM\EntityRepository;

class GameSaveRepository extends EntityRepository
{
    public function save(GameSave $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameSave $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
