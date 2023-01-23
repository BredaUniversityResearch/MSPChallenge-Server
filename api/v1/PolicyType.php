<?php

namespace App\Domain\API\v1;

use App\Domain\Common\Enum;

class PolicyType extends Enum
{
    // should be bit flags!
    public const ENERGY = 1;
    public const FISHING = 2; // = ecology
    public const SHIPPING = 4;
}
