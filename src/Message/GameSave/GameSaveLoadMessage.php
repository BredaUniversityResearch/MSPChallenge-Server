<?php

namespace App\Message\GameSave;

readonly class GameSaveLoadMessage
{
    public function __construct(
        public int $id,
        public int $gameSaveId
    ) {
        // note that $id refers to the GameList/session ID that needs to be load based on $gameSaveId
    }
}
