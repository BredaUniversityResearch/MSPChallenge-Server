<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\Enum;

class GameSaveVisibilityValue extends Enum
{
    public const ACTIVE = 'active';
    public const ARCHIVED = 'archived';
}
