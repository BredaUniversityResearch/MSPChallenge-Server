<?php

namespace App\Domain\Common\EntityEnums;

enum WarningIssueType: string
{
    case ERROR = 'Error';
    case WARNING = 'Warning';
    case INFO = 'Info';
    case NONE = 'None';
}
