<?php

namespace App\Repository;

use App\Entity\PolicyFilterLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PolicyFilterLink>
 *
 * @method PolicyFilterLink|null find($id, $lockMode = null, $lockVersion = null)
 * @method PolicyFilterLink|null findOneBy(array $criteria, array $orderBy = null)
 * @method PolicyFilterLink[]    findAll()
 * @method PolicyFilterLink[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PolicyFilterLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PolicyFilterLink::class);
    }

//    /**
//     * @return PolicyFilterLink[] Returns an array of PolicyFilterLink objects
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

//    public function findOneBySomeField($value): ?PolicyFilterLink
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
