<?php

namespace App\Domain\API\v1;

use App\Domain\Common\Enum;

class GeneralPolicyType extends Enum
{
    // should be bit flags!
    public const ENERGY = 1;
    public const FISHING = 2; // = ecology = fishing effort
    public const SHIPPING = 4;
    public const ECO_GEAR = 8;
}
