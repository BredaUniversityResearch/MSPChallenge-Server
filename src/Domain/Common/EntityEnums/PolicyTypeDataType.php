<?php

namespace App\Domain\Common\EntityEnums;

enum PolicyTypeDataType: string
{
    case Boolean = 'boolean';
    case Ranged = 'ranged';
    case Temporal = 'temporal';
}
