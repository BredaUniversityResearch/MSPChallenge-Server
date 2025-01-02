<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameConfigFile;
use App\Entity\ServerManager\GameConfigVersion;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

class GameConfigVersionRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
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

    public function findLatestVersion(GameConfigFile $gameConfigFile): ?GameConfigVersion
    {
        return $this->createQueryBuilder('gcv')
            ->andWhere('gcv.gameConfigFile = :val')
            ->orderBy('gcv.version', 'DESC')
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->setParameter('val', $gameConfigFile)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function orderedList(array $visibility): array
    {
        return $this->createQueryBuilder('gcv')
            ->innerJoin('gcv.gameConfigFile', 'gcf')
            ->andWhere('gcv.visibility = :val')
            ->setParameter('val', $visibility)
            ->addOrderBy('gcf.filename', 'ASC')
            ->addOrderBy('gcv.version', 'ASC')
            ->getQuery()
            ->getResult()
        ;
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
