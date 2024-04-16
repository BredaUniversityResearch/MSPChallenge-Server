<?php

namespace App\Repository;

use App\Entity\PolicyLayer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PolicyLayer>
 *
 * @method PolicyLayer|null find($id, $lockMode = null, $lockVersion = null)
 * @method PolicyLayer|null findOneBy(array $criteria, array $orderBy = null)
 * @method PolicyLayer[]    findAll()
 * @method PolicyLayer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PolicyLayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PolicyLayer::class);
    }

//    /**
//     * @return PolicyLayer[] Returns an array of PolicyLayer objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PolicyLayer
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
