<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use App\Entity\EventLog;

class LoggableEntityRepository extends EntityRepository
{
    public function findLogs(int $id)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('el')
            ->from(EventLog::class, 'el')
            ->where('el.referenceObject = :referenceObject')
            ->andWhere('el.referenceId = :referenceId')
            ->setParameter('referenceObject', $this->getEntityName())
            ->setParameter('referenceId', $id);

        return $qb->getQuery()->getResult();
    }
}
