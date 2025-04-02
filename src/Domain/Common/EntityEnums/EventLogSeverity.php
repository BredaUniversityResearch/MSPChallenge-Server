<?php

namespace App\Domain\Common\EntityEnums;

enum EventLogSeverity : string
{
    case WARNING = 'Warning';
    case ERROR = 'Error';
    case FATAL = 'Fatal';
}
