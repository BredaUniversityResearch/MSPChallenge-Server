<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\EntityEnums\Attribute\Description;
use App\Domain\Common\EntityEnums\Attribute\GetAttributesTrait;

enum GeoServerAccessType: string
{
    use GetAttributesTrait;

    #[Description('Anonymous access')]
    case ANONYMOUS = 'anonymous';

    #[Description('Use credentials')]
    case CREDENTIALS = 'credentials';
}
