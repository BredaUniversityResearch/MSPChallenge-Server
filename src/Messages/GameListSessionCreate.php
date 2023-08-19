<?php

namespace App\Messages;

use App\Entity\ServerManager\GameList;

class GameListSessionCreate extends GameList
{
    public function __construct(int $id)
    {
        parent::__construct($id);
    }
}
