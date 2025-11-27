<?php

namespace App\Repository\SessionAPI;

use App\Entity\SessionAPI\EventLog;

/**
 * @template T of object
 * @extends SessionEntityRepository<T>
 */
class LoggableEntityRepository extends SessionEntityRepository
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
