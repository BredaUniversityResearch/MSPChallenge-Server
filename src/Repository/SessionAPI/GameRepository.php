<?php

namespace App\src\Repository\SessionAPI;

use App\src\Entity\SessionAPI\Game;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

class GameRepository extends EntityRepository
{
    public function save(Game $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Game $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function retrieve(): Game
    {
        return $this->createQueryBuilder('g')
            ->getQuery()
            ->getSingleResult()
        ;
    }
}
