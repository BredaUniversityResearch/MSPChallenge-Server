<?php

namespace App\Domain\Common\EntityEnums;

enum ViewingSessionState: string
{
    case INACTIVE = 'INACTIVE';
    case ACTIVE = 'ACTIVE';
}
