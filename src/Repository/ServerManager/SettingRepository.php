<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\Setting;
use Doctrine\ORM\EntityRepository;

class SettingRepository extends EntityRepository
{
    public function save(Setting $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Setting $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
