<?php

namespace App\Repository;

use App\Entity\Watchdog;
use Symfony\Component\Uid\Uuid;

class WatchdogRepository extends LoggableEntityRepository
{
    public function registerWatchdog(
        Uuid $serverId,
        string $address,
        int $port,
        string $scheme
    ): void {
        $watchdog = $this->findOneBy(['serverId' => $serverId]);
        $watchdog ??= new Watchdog();

        $watchdog->setServerId($serverId);
        $watchdog->setAddress($address);
        $watchdog->setPort($port);
        $watchdog->setToken(0); // this is temporary and will be updated later
        $watchdog->setScheme($scheme);

        $this->getEntityManager()->persist($watchdog);
        $this->getEntityManager()->flush();

        // update watchdog record with token using DQL
        $qb = $this->createQueryBuilder('w');
        $qb
            ->update()
            ->set('w.token', 'UUID_SHORT()')
            ->where($qb->expr()->eq('w.serverId', ':serverId'))
            ->setParameter('serverId', $serverId->toBinary())
            ->getQuery()
            ->execute();
    }
}
