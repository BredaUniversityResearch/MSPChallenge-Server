<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\Enum;

class GameStateValue extends Enum
{
    public const SETUP = 'setup';
    public const SIMULATION = 'simulation';
    public const PLAY = 'play';
    public const PAUSE = 'pause';
    public const END = 'end';
    public const FASTFORWARD = 'fastforward';
}
