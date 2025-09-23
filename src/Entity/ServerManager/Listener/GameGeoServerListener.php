<?php

namespace App\Entity\ServerManager\Listener;

use App\Domain\Communicator\Auth2Communicator;
use App\Entity\ServerManager\GameGeoServer;
use App\Entity\ServerManager\Setting;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GameGeoServerListener
{
    public function __construct(
        private readonly HttpClientInterface $client
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
        //if it's the BUas GeoServer, then get its username and password
        if ($geoServer->getId() == 1) {
            $settingRepo = $event->getObjectManager()->getRepository(Setting::class);
            $auth2Communicator = new Auth2Communicator($this->client);
            $auth2Communicator->setUsername($settingRepo->findOneBy(['name' => 'server_id'])->getValue());
            $auth2Communicator->setPassword($settingRepo->findOneBy(['name' => 'server_password'])->getValue());
            $auth2Result = $auth2Communicator->getResource('geo_servers');
            if (!empty($auth2Result['hydra:member'])) {
                $geoServer->setAddress($auth2Result['hydra:member'][0]['baseurl'] ?? '');
                $geoServer->setUsername($auth2Result['hydra:member'][0]['username'] ?? '');
                $geoServer->setPassword(
                    openssl_decrypt(
                        $auth2Result['hydra:member'][0]['password'] ?? '',
                        'aes-128-cbc',
                        $auth2Communicator->getToken(),
                        0,
                        decbin(65535)
                    )
                );
            }
        }
    }
}
