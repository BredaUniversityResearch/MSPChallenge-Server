<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\EntityEnums\Attribute\GetAttributesTrait;

enum ImmersiveSessionConnectionStatus: string
{
    use GetAttributesTrait;

    public const ALL = [
        self::STARTING->value,
        self::RUNNING->value,
        self::UNRESPONSIVE->value
    ];

    case STARTING = 'starting';

    case RUNNING = 'running';

    case UNRESPONSIVE = 'unresponsive';
}
