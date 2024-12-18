<?php

namespace App\Domain\Common\EntityEnums;

enum EventLogSeverity : string
{
    case WARNING = 'warning';
    case ERROR = 'error';
    case FATAL = 'fatal';
}
