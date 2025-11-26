<?php

namespace App\Entity\ServerManager\Listener;

use App\Entity\ServerManager\GameGeoServer;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;

readonly class GameGeoServerListener
{
    public function __construct(
        private AbstractVault $vault
    ) {
    }

    public function preFlush(GameGeoServer $geoServer, PreFlushEventArgs $event): void
    {
        if (substr($geoServer->getAddress(), -1) !== '/') {
            $geoServer->setAddress($geoServer->getAddress().'/');
        }
    }

    public function postLoad(GameGeoServer $geoServer, PostLoadEventArgs $event): void
    {
        $geoServer->setVault($this->vault);
    }
}
