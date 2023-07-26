<?php

namespace App\Entity\ServerManager\Listeners;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Entity\ServerManager\GameGeoServer;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\ServerManager\GameList;
use Doctrine\ORM\Event\PrePersistEventArgs;

class GameListListener
{
    public function prePersist(GameList $gameSession, PrePersistEventArgs $event): void
    {
        $gameConfigContentComplete = $gameSession->getGameConfigVersion()->getGameConfigComplete();
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
