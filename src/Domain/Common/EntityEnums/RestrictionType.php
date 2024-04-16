<?php

namespace App\Domain\Common\EntityEnums;

enum RestrictionType: string
{
    case ERROR = 'ERROR';
    case WARNING = 'WARNING';
    case INFO = 'INFO';
}
