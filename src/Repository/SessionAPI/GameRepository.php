<?php

namespace App\Repository\SessionAPI;

use App\Entity\SessionAPI\Game;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @extends SessionEntityRepository<Game>
 */
class GameRepository extends SessionEntityRepository
{
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
