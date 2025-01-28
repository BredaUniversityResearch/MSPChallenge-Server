<?php

namespace App\Repository;

use App\Domain\Common\EntityEnums\WatchdogStatus;

class WatchdogRepository extends LoggableEntityRepository
{
    public function removeUnresponsiveWatchdogs(): void
    {
        $watchdogs = $this->findBy(['status' => WatchdogStatus::UNRESPONSIVE]);
        foreach ($watchdogs as $watchdog) {
            $this->getEntityManager()->remove($watchdog);
        }
        $this->getEntityManager()->flush();
    }
}
