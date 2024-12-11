<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameConfigFile;
use Doctrine\ORM\EntityRepository;

class GameConfigFileRepository extends EntityRepository
{

    public function save(GameConfigFile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameConfigFile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
