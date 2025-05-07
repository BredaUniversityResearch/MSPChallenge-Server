<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\EntityEnums\Attribute\Description;
use App\Domain\Common\EntityEnums\Attribute\GetAttributesTrait;

enum ImmersiveSessionTypeID: string
{
    use GetAttributesTrait;

    public const ALL = [
        self::MIXED_REALITY->value,
    ];

    #[Description('Mixed Reality')]
    case MIXED_REALITY = 'mixed-reality';
}
