<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\GameConfigFile;
use App\Entity\ServerManager\GameConfigVersion;
use Doctrine\ORM\EntityRepository;

class GameConfigVersionRepository extends EntityRepository
{
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
}
