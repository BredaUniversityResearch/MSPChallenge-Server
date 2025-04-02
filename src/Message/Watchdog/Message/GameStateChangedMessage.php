<?php

namespace App\Message\Watchdog\Message;

use App\Domain\Common\EntityEnums\GameStateValue;

class GameStateChangedMessage extends GameMonthChangedMessage
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
