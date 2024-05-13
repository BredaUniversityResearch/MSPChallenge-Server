<?php

namespace App\Domain\Common\EntityEnums;

enum PolicyTypeDataType: string
{
    case None = 'none';
    case Boolean = 'boolean';
    case Ranged = 'ranged';
}
