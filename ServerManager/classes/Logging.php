<?php

namespace ServerManager;

class Logging
{
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function LogError($message): void
    {
        echo $message;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function Verbose($message): void
    {
        if (is_array($message)) {
            print_r($message);
            echo "\n";
        } else {
            echo $message."\n";
        }
    }
}
