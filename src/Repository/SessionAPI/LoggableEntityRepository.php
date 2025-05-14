<?php

namespace App\src\Repository\SessionAPI;

use App\src\Entity\SessionAPI\EventLog;
use Doctrine\ORM\EntityRepository;

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
