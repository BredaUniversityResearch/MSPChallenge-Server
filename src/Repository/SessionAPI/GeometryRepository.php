<?php

namespace App\src\Repository\SessionAPI;

use App\Entity\SessionAPI\Geometry;
use Doctrine\ORM\EntityRepository;

class GeometryRepository extends EntityRepository
{
    public function save(Geometry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Geometry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
