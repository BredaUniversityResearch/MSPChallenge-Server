<?php

namespace App\Repository\ServerManager;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Entity\ServerManager\GameList;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

class GameListRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }

    public function save(GameList $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameList $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return GameList[] Returns an array of GameList objects by session state, archived or not archived (active)
     */
    public function findBySessionState(string $value): array
    {
        $qb = $this->createQueryBuilder('g');
        if ($value == 'archived') {
            $qb->andWhere($qb->expr()->eq('g.sessionState', ':val'))
                ->setParameter('val', new GameSessionStateValue('archived'));
        } else {
            $qb->andWhere($qb->expr()->neq('g.sessionState', ':val'))
                ->setParameter('val', new GameSessionStateValue('archived'));
        }
        return $qb->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

//    public function findOneBySomeField($value): ?GameList
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
