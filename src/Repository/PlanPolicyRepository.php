<?php

namespace App\Repository;

use App\Entity\PlanPolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanPolicy>
 *
 * @method PlanPolicy|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlanPolicy|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlanPolicy[]    findAll()
 * @method PlanPolicy[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlanPolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanPolicy::class);
    }

//    /**
//     * @return PlanPolicy[] Returns an array of PlanPolicy objects
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

//    public function findOneBySomeField($value): ?PlanPolicy
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
