<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameGeoServer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameGeoServer>
 *
 * @method GameGeoServer|null find($id, $lockMode = null, $lockVersion = null)
 * @method GameGeoServer|null findOneBy(array $criteria, array $orderBy = null)
 * @method GameGeoServer[]    findAll()
 * @method GameGeoServer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GameGeoServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameGeoServer::class);
    }

    public function save(GameGeoServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameGeoServer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return GameGeoServer[] Returns an array of GameGeoServer objects
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

//    public function findOneBySomeField($value): ?GameGeoServer
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
