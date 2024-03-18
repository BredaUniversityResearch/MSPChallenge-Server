<?php

namespace App\Repository;

use App\Entity\PolicyTypeFilterTypeLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PolicyTypeFilterTypeLink>
 *
 * @method PolicyTypeFilterTypeLink|null find($id, $lockMode = null, $lockVersion = null)
 * @method PolicyTypeFilterTypeLink|null findOneBy(array $criteria, array $orderBy = null)
 * @method PolicyTypeFilterTypeLink[]    findAll()
 * @method PolicyTypeFilterTypeLink[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PolicyTypeFilterTypeLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PolicyTypeFilterTypeLink::class);
    }

//    /**
//     * @return PolicyTypeFilterTypeLink[] Returns an array of PolicyTypeFilterTypeLink objects
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

//    public function findOneBySomeField($value): ?PolicyTypeFilterTypeLink
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
