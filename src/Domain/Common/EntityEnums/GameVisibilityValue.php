<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\Enum;

class GameVisibilityValue extends Enum
{
    public const PUBLIC = 'public';
    public const PRIVATE = 'private';
}
