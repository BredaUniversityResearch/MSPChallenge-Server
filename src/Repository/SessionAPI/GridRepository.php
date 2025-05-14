<?php

namespace App\src\Repository\SessionAPI;

use App\src\Entity\SessionAPI\Grid;
use Doctrine\ORM\EntityRepository;

class GridRepository extends EntityRepository
{
    public function save(Grid $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Grid $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
