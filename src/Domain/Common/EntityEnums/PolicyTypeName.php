<?php

namespace App\Domain\Common\EntityEnums;

enum PolicyTypeName: string
{
    case BUFFER_ZONE = 'buffer_zone';
    case SEASONAL_CLOSURE = 'seasonal_closure';
    case ECO_GEAR = 'eco_gear';
}
