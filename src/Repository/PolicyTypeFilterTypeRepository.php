<?php

namespace App\Repository;

use App\Entity\PolicyTypeFilterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PolicyTypeFilterType>
 *
 * @method PolicyTypeFilterType|null find($id, $lockMode = null, $lockVersion = null)
 * @method PolicyTypeFilterType|null findOneBy(array $criteria, array $orderBy = null)
 * @method PolicyTypeFilterType[]    findAll()
 * @method PolicyTypeFilterType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PolicyTypeFilterTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PolicyTypeFilterType::class);
    }

//    /**
//     * @return PolicyTypeFilterType[] Returns an array of PolicyTypeFilterType objects
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

//    public function findOneBySomeField($value): ?PolicyTypeFilterType
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
