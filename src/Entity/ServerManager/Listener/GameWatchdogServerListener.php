<?php

namespace App\Entity\ServerManager\Listener;

use App\Entity\ServerManager\GameWatchdogServer;
use Doctrine\ORM\Event\PreFlushEventArgs;

class GameWatchdogServerListener
{
    public function preFlush(GameWatchdogServer $watchdogServer, PreFlushEventArgs $event): void
    {
        if (substr($watchdogServer->getAddress(), -1) !== '/') {
            $watchdogServer->setAddress($watchdogServer->getAddress().'/');
        }
    }
}
