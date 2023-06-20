<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\Enum;

class GameSessionStateValue extends Enum
{
    public const REQUEST = 'request';
    public const INITIALIZING = 'initializing';
    public const HEALTHY = 'healthy';
    public const FAILED = 'failed';
    public const ARCHIVED = 'archived';
}
