<?php

namespace App\Domain\Common\EntityEnums;

use App\Domain\Common\Enum;

class PolicyTypeValue extends Enum
{
    // should be bit flags!
    public const ENERGY = 1;
    public const FISHING = 2; // = ecology
    public const SHIPPING = 4;
}
