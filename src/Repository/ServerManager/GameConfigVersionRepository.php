<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameConfigVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameConfigVersion>
 *
 * @method GameConfigVersion|null find($id, $lockMode = null, $lockVersion = null)
 * @method GameConfigVersion|null findOneBy(array $criteria, array $orderBy = null)
 * @method GameConfigVersion[]    findAll()
 * @method GameConfigVersion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GameConfigVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameConfigVersion::class);
    }

    public function save(GameConfigVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameConfigVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return GameConfigVersion[] Returns an array of GameConfigVersion objects
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

//    public function findOneBySomeField($value): ?GameConfigVersion
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
