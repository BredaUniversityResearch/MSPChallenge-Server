<?php

namespace App\Domain\WsServer;

class WsServerDebugOutput
{
    /**
     * WsServer debugging output if enabled by WS_SERVER_DEBUG_OUTPUT via .env file
     */
    public static function output(string $message): void
    {
        if (empty($_ENV['WS_SERVER_DEBUG_OUTPUT'])) {
            return;
        }
        echo $message . PHP_EOL;
    }
}
