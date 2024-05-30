<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\EntityEnums\Attribute\Description;
use App\Domain\Common\EntityEnums\Attribute\GetAttributesTrait;

enum PolicyTypeName: string
{
    use GetAttributesTrait;

    #[Description('Buffer zone')]
    case BUFFER_ZONE = 'buffer_zone';
    #[Description('Seasonal closure')]
    case SEASONAL_CLOSURE = 'seasonal_closure';
    #[Description('Ecological fishing gear')]
    case ECO_GEAR = 'eco_gear';
}
