<?php

namespace App\Message\GameList;

class GameListCreationMessage
{
    public function __construct(public readonly int $id)
    {
    }
}