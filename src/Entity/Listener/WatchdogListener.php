<?php

namespace App\Entity\Listener;

use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\Watchdog;
use App\Repository\ServerManager\GameWatchdogServerRepository;
use Doctrine\ORM\Event\PostLoadEventArgs;

class WatchdogListener implements SubEntityListenerInterface, PostLoadEventListenerInterface
{
    private static ?WatchdogListener $instance = null;

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
            Watchdog::class,
        ];
    }

    public function triggerPostLoad(Watchdog $watchdog): void
    {
        $watchdog->hasLazyLoader(Watchdog::LAZY_LOADING_PROPERTY_GAME_WATCHDOG_SERVER) or
        $watchdog->setLazyLoader(
            Watchdog::LAZY_LOADING_PROPERTY_GAME_WATCHDOG_SERVER,
            function () use ($watchdog) {
                $em = ConnectionManager::getInstance()->getServerManagerEntityManager();
                /** @var GameWatchdogServerRepository $repo */
                $repo = $em->getRepository(GameWatchdogServer::class);
                return $repo->findOneBy(['serverId' => $watchdog->getServerId()]);
            }
        );
    }

    public function postLoad(PostLoadEventArgs $event): void
    {
        $watchdog = $event->getObject();
        if (!$watchdog instanceof Watchdog) {
            return;
        }
        $this->triggerPostLoad($watchdog);
    }
}
