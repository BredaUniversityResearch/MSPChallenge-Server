<?php

namespace App\Entity\ServerManager\Listener;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameGeoServer;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\SessionAPI\Game;
use App\src\Repository\SessionAPI\GameRepository;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;

class GameListListener
{
    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {
    }

    public function preFlush(GameList $gameSession, PreFlushEventArgs $event): void
    {
        if (is_null($gameSession->getPasswordPlayer())) {
            $gameSession->setPasswordPlayer('');
        }
        $gameSession->encodePasswords();
    }

    public function postLoad(GameList $gameSession, PostLoadEventArgs $event): void
    {
        $gameSession->decodePasswords();
        try {
            /** @var GameRepository $repo */
            $repo = $this->connectionManager->getGameSessionEntityManager($gameSession->getId())
                ->getRepository(Game::class);
            $gameSession->setRunningGame($repo->retrieve());
        } catch (\Throwable) {
            // This could happen when session DB is still being created or has gotten corrupted.
            $gameSession->setRunningGame(null);
        }
    }

    public function prePersist(GameList $gameSession, PrePersistEventArgs $event): void
    {
        $gameConfigContentComplete = $gameSession->getGameConfigVersion()?->getGameConfigComplete();
        if (is_null($gameSession->getGameCreationTime())) {
            $gameSession->setGameCreationTime(time());
        }
        if (is_null($gameSession->getGameStartYear())) {
            $gameSession->setGameStartYear($gameConfigContentComplete['datamodel']['start'] ?? 2000);
        }
        if (is_null($gameSession->getGameEndMonth())) {
            $gameSession->setGameEndMonth(
                ($gameConfigContentComplete['datamodel']['era_total_months'] ?? 150) * 4
            );
        }
        if (is_null($gameSession->getGameRunningTilTime())) {
            $gameSession->setGameRunningTilTime(
                ($gameConfigContentComplete['datamodel']['era_planning_realtime'] ?? 7200) * 4
            );
        }
        if (is_null($gameSession->getSessionState())) {
            $gameSession->setSessionState(new GameSessionStateValue('request'));
        }
        if (is_null($gameSession->getGameState())) {
            $gameSession->setGameState(new GameStateValue('setup'));
        }
        if (is_null($gameSession->getGameVisibility())) {
            $gameSession->setGameVisibility(new GameVisibilityValue('public'));
        }
        if (is_null($gameSession->getGameServer())) {
            $gameSession->setGameServer(
                $event->getObjectManager()->getRepository(GameServer::class)->findOneBy(['id' => 1])
            );
        }
        if (is_null($gameSession->getGameGeoServer())) {
            $gameSession->setGameGeoServer(
                $event->getObjectManager()->getRepository(GameGeoServer::class)->findOneBy(['id' => 1])
            );
        }
        if (is_null($gameSession->getGameWatchdogServer())) {
            $gameSession->setGameWatchdogServer(
                $event->getObjectManager()->getRepository(GameWatchdogServer::class)->findOneBy(['id' => 1])
            );
        }
    }
}
