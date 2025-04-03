<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\EntityEnums\Attribute\Description;

enum ViewingDeviceType: string
{
    #[Description('Mixed Reality')]
    case MR = 'MR';
    #[Description('Virtual Reality')]
    case VR = 'VR';
}
