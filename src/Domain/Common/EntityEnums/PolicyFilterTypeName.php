<?php

namespace App\Domain\Common\EntityEnums;

enum PolicyFilterTypeName: string
{
    case FLEET = 'fleet';
    case SCHEDULE = 'schedule';
}
