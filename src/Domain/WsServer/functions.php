<?php

use App\Domain\WsServer\WsServerDebugOutput;

function wdo(string $message)
{
    WsServerDebugOutput::output($message);
}
