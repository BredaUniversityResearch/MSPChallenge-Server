<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\EntityEnums\Attribute\Description;
use App\Domain\Common\EntityEnums\Attribute\GetAttributesTrait;

enum ImmersiveSessionTypeID: string
{
    use GetAttributesTrait;

    public const ALL = [
        self::AR->value,
        self::MR->value,
        self::VR->value,
        self::XR->value
    ];

    #[Description('Augmented Reality')]
    case AR = 'ar';

    #[Description('Mixed Reality')]
    case MR = 'mr';

    #[Description('Virtual Reality')]
    case VR = 'vr';

    #[Description('Extended Reality')]
    case XR = 'xr';
}
