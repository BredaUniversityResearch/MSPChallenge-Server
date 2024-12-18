<?php

namespace App\Repository;

use App\Entity\Restriction;
use Doctrine\ORM\EntityRepository;

class RestrictionRepository extends EntityRepository
{
    public function save(Restriction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Restriction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
