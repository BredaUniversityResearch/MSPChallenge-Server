<?php

namespace App\Domain\Common\EntityEnums;

enum RestrictionSort: string
{
    case INCLUSION = 'INCLUSION';
    case EXCLUSION = 'EXCLUSION';
    case TYPE_UNAVAILABLE = 'TYPE_UNAVAILABLE';
}
