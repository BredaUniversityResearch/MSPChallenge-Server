<?php

namespace App\Domain\Common\EntityEnums;

enum GameStateValue: string
{
    case SETUP = 'SETUP';
    case SIMULATION = 'SIMULATION';
    case PLAY = 'PLAY';
    case PAUSE = 'PAUSE';
    case END = 'END';
    case FASTFORWARD = 'FASTFORWARD';
}
