<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\Enum;

class GameConfigVersionVisibilityValue extends Enum
{
    public const VISIBLE = 'visible';
    public const ARCHIVED = 'archived';
}
