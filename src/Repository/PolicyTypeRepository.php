<?php

namespace App\Repository;

use App\Entity\PolicyType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PolicyType>
 *
 * @method PolicyType|null find($id, $lockMode = null, $lockVersion = null)
 * @method PolicyType|null findOneBy(array $criteria, array $orderBy = null)
 * @method PolicyType[]    findAll()
 * @method PolicyType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PolicyTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PolicyType::class);
    }

//    /**
//     * @return PolicyType[] Returns an array of PolicyType objects
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

//    public function findOneBySomeField($value): ?PolicyType
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
