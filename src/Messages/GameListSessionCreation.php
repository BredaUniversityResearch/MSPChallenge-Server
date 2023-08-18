<?php

namespace App\Messages;

use App\Entity\ServerManager\GameList;

class GameListSessionCreation extends GameList
{
    public function __construct(int $id)
    {
        parent::__construct($id);
    }
}
