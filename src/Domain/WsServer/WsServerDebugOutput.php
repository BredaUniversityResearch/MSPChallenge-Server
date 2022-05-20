<?php

namespace App\Domain\WsServer;

class WsServerDebugOutput
{
    private static ?string $messageFilter = null;

    public static function setMessageFilter(?string $messageFilter)
    {
        self::$messageFilter = $messageFilter;
    }

    /**
     * WsServer debugging output if enabled by WS_SERVER_DEBUG_OUTPUT via .env file
     */
    public static function output(string $message): void
    {
        if (empty($_ENV['WS_SERVER_DEBUG_OUTPUT'])) {
            return;
        }

        if (self::$messageFilter != null && false === strpos($message, self::$messageFilter)) {
            return;
        }

        $mSec = '0000';
        if ((false !== $parts = explode('.', microtime(true))) && count($parts) > 1) {
            $mSec = $parts[1];
        }
        echo '[' . date('H:i:s.') . sprintf("%'.04d", $mSec) . '] ' . $message . PHP_EOL;
    }
}
