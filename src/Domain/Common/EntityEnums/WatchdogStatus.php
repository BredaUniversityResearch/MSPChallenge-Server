<?php

namespace App\Domain\Common\EntityEnums;

enum WatchdogStatus: string
{
    case REGISTERED = 'registered';
    case READY = 'ready';
    case UNRESPONSIVE = 'unresponsive';
    case UNREGISTERED = 'unregistered';
}