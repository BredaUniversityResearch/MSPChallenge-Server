<?php

namespace App\Entity\ServerManager\Listeners;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use Doctrine\ORM\Event\PrePersistEventArgs;
use App\Entity\ServerManager\GameList;

class GameListListener
{
    public function prePersist(GameList $gameList, PrePersistEventArgs $event)
    {
        $gameConfig = $gameList->getGameConfigVersion();
        $gameConfigContents = $gameConfig->getContents();
        if (is_null($gameConfigContents)) {
            return;
        }

        if (is_null($gameList->getGameCreationTime())) {
            $gameList->setGameCreationTime(time());
        }
        if (is_null($gameList->getGameStartYear())) {
            $gameList->setGameStartYear($gameConfigContents['datamodel']['start'] ?? 2000);
        }
        if (is_null($gameList->getGameEndMonth())) {
            $gameList->setGameEndMonth(
                ($gameConfigContents['datamodel']['era_total_months'] ?? 150) * 4
            );
        }
        if (is_null($gameList->getGameRunningTilTime())) {
            $gameList->setGameRunningTilTime(
                ($gameConfigContents['datamodel']['era_planning_realtime'] ?? 7200) * 4
            );
        }
        if (is_null($gameList->getSessionState())) {
            $gameList->setSessionState(new GameSessionStateValue('request'));
        }
        if (is_null($gameList->getGameState())) {
            $gameList->setGameState(new GameStateValue('setup'));
        }
        if (is_null($gameList->getGameVisibility())) {
            $gameList->setGameVisibility(new GameVisibilityValue('public'));
        }
    }
}
