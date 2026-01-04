<?php

namespace App\Domain\Common\EntityEnums;

enum GameTransitionStateValue: string
{
    case REQUEST = 'REQUEST';
    case SETUP = 'SETUP';
    case SIMULATION = 'SIMULATION';
    case PLAY = 'PLAY';
    case PAUSE = 'PAUSE';
    case END = 'END'; // this value is probably not needed
    case FASTFORWARD = 'FASTFORWARD';
}
