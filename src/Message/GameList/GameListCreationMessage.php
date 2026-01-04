<?php

namespace App\Message\GameList;

readonly class GameListCreationMessage
{
    public function __construct(
        public int $id
    ) {
    }
}
