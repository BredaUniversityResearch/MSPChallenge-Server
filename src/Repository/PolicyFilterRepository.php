<?php

namespace App\Repository;

use App\Entity\PolicyFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PolicyFilter>
 *
 * @method PolicyFilter|null find($id, $lockMode = null, $lockVersion = null)
 * @method PolicyFilter|null findOneBy(array $criteria, array $orderBy = null)
 * @method PolicyFilter[]    findAll()
 * @method PolicyFilter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PolicyFilterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PolicyFilter::class);
    }

//    /**
//     * @return PolicyFilter[] Returns an array of PolicyFilter objects
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

//    public function findOneBySomeField($value): ?PolicyFilter
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
