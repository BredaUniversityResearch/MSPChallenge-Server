<?php

namespace App\Domain\Common\EntityEnums;

enum PlanLayerState: string
{
    case ACTIVE = 'ACTIVE';
    case ASSEMBLY = 'ASSEMBLY';
    case WAIT = 'WAIT';
}
