<?php

namespace App\Message\Watchdog;

use App\Domain\Common\EntityEnums\GameStateValue;

class GameStateChangedMessage extends WatchdogMessageBase
{
    private GameStateValue $gameState;

    public function getGameState(): GameStateValue
    {
        return $this->gameState;
    }

    public function setGameState(GameStateValue $gameState): self
    {
        $this->gameState = $gameState;
        return $this;
    }
}
