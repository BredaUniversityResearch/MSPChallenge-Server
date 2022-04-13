<?php

namespace App\Domain\WsServer;

use App\Domain\Common\Enum;

class EPayloadDifferenceType extends Enum
{
    const NO_DIFFERENCES = 0;
    const NONESSENTIAL_DIFFERENCES = 1;
    const ESSENTIAL_DIFFERENCES = 2;
}
