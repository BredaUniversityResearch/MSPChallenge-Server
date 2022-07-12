<?php

namespace App\Domain\WsServer\Plugins\Tick;

use App\Domain\Common\CommonBase;
use React\Promise\PromiseInterface;

abstract class TickBase extends CommonBase
{
    abstract public function tick(bool $showDebug = false): PromiseInterface;
}
