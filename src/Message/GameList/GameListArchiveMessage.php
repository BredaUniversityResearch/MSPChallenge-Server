<?php

namespace App\Message\GameList;

class GameListArchiveMessage
{
    public function __construct(public readonly int $id)
    {
    }
}
