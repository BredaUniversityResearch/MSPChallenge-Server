<?php

namespace App\Message\GameList;

readonly class GameListCreationMessage
{
    public function __construct(
        public int   $id,
        private bool $isDemoSession = false
    ) {
    }

    public function isDemoSession(): bool
    {
        return $this->isDemoSession;
    }
}
