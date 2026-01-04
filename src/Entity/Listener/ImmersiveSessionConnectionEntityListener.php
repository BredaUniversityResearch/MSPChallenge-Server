<?php

namespace App\Entity\Listener;

use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\DockerApi;
use App\Entity\SessionAPI\DockerConnection;
use Doctrine\ORM\Event\PostLoadEventArgs;

class ImmersiveSessionConnectionEntityListener implements SubEntityListenerInterface, PostLoadEventListenerInterface
{
    private static ?self $instance = null;

    public function __construct()
    {
        self::$instance = $this;
    }

    public static function getInstance(): self
    {
        self::$instance ??= new self();
        return self::$instance;
    }

    public function getSupportedEntityClasses(): array
    {
        return [
            DockerConnection::class,
        ];
    }

    public function triggerPostLoad(DockerConnection $immersiveSessionConnection): void
    {
        $immersiveSessionConnection->hasLazyLoader(DockerConnection::LAZY_LOADING_PROPERTY_DOCKER_API) or
        $immersiveSessionConnection->setLazyLoader(
            DockerConnection::LAZY_LOADING_PROPERTY_DOCKER_API,
            function () use ($immersiveSessionConnection) {
                $em = ConnectionManager::getInstance()->getServerManagerEntityManager();
                $repo = $em->getRepository(DockerApi::class);
                return $repo->find($immersiveSessionConnection->getDockerApiID());
            }
        );
    }

    public function postLoad(PostLoadEventArgs $event): void
    {
        $immersiveSessionConnection = $event->getObject();
        if (!$immersiveSessionConnection instanceof DockerConnection) {
            return;
        }
        $this->triggerPostLoad($immersiveSessionConnection);
    }
}
