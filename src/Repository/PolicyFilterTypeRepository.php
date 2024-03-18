<?php

namespace App\Repository;

use App\Entity\PolicyFilterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PolicyFilterType>
 *
 * @method PolicyFilterType|null find($id, $lockMode = null, $lockVersion = null)
 * @method PolicyFilterType|null findOneBy(array $criteria, array $orderBy = null)
 * @method PolicyFilterType[]    findAll()
 * @method PolicyFilterType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PolicyFilterTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PolicyFilterType::class);
    }

//    /**
//     * @return PolicyFilterType[] Returns an array of PolicyFilterType objects
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

//    public function findOneBySomeField($value): ?PolicyFilterType
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
