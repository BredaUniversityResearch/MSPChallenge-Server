<?php

namespace App\Message\GameSave;

class GameSaveLoadMessage
{
    public function __construct(
        public readonly int $id,
        public readonly int $gameSaveId
    ) {
        // note that $id refers to the GameList/session ID that needs to be load based on $gameSaveId
    }
}
