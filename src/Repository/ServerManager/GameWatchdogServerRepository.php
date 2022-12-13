<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameWatchdogServer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameWatchdogServer>
 *
 * @method GameWatchdogServer|null find($id, $lockMode = null, $lockVersion = null)
 * @method GameWatchdogServer|null findOneBy(array $criteria, array $orderBy = null)
 * @method GameWatchdogServer[]    findAll()
 * @method GameWatchdogServer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GameWatchdogServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameWatchdogServer::class);
    }

    public function save(GameWatchdogServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameWatchdogServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return GameWatchdogServer[] Returns an array of GameWatchdogServer objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('g.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?GameWatchdogServer
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
