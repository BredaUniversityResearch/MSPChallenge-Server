<?php

namespace App\Domain\WsServer;

use Symfony\Component\Console\Output\OutputInterface;

class WsServerOutput
{
    public const VERBOSITY_DEFAULT_MESSAGE = OutputInterface::VERBOSITY_VERBOSE;
    private const VERBOSITY_DEFAULT_OUTPUT = OutputInterface::VERBOSITY_NORMAL;

    private static ?int $verbosity = null;
    private static ?string $messageFilter = null;

    public static function getVerbosity(): int
    {
        if (self::$verbosity === null && array_key_exists('WS_SERVER_OUTPUT_VERBOSITY', $_ENV)) {
            self::$verbosity = (int)$_ENV['WS_SERVER_OUTPUT_VERBOSITY'];
        }
        return self::$verbosity ?? self::VERBOSITY_DEFAULT_OUTPUT;
    }

    public static function setVerbosity(int $verbosity): void
    {
        if ($verbosity == self::VERBOSITY_DEFAULT_OUTPUT) {
            return;
        }
        self::$verbosity = $verbosity;
    }

    public static function setMessageFilter(?string $messageFilter)
    {
        self::$messageFilter = $messageFilter;
    }

    public static function output(string $message, int $verbosity = self::VERBOSITY_DEFAULT_MESSAGE): void
    {
        if ($verbosity > self::getVerbosity()) {
            return;
        }

        if (self::$messageFilter != null && false === strpos($message, self::$messageFilter)) {
            return;
        }

        $mSec = '0000';
        if ((false !== $parts = explode('.', sprintf('%.4f', microtime(true)))) && count($parts) > 1) {
            $mSec = $parts[1];
        }
        echo '[' . date('H:i:s.') . sprintf("%'.04d", $mSec) . '] ' . $message . PHP_EOL;
    }
}
