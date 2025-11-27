<?php

namespace App\Tests\Utils;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ResourceHelper
{
    const MAX_GAME_SESSION_COUNT = 9;

    const OPTION_GAME_SESSION_COUNT = 'gameSessionCount';

    public static function resetDatabases(string $projectDir, array $options = [])
    {
        $options[self::OPTION_GAME_SESSION_COUNT] ??= 0;
        $phpBinary ??= (new PhpExecutableFinder)->find(false);
        if ($phpBinary === false) {
            throw new \RuntimeException('The php binary could not be found.');
        }
        self::dropDatabase($_ENV['DBNAME_SERVER_MANAGER'], $phpBinary, $projectDir);
        for ($n = 1; $n <= self::MAX_GAME_SESSION_COUNT; $n++) {
            self::dropDatabase("msp_session_$n", $phpBinary, $projectDir);
        }
        self::createDatabase($_ENV['DBNAME_SERVER_MANAGER'], $phpBinary, $projectDir);
        for ($n = 1; $n <= $options[self::OPTION_GAME_SESSION_COUNT]; $n++) {
            self::createDatabase("msp_session_$n", $phpBinary, $projectDir);
        }
    }

    public static function dropDatabase(string $databaseName, string $phpBinary, string $projectDir): void
    {
        $process = new Process([
            $phpBinary,
            'bin/console',
            'doctrine:database:drop',
            '--connection='.$databaseName,
            '--if-exists',
            '--force',
            '--no-interaction',
            '--env='.$_ENV['APP_ENV']
        ], $projectDir);
        $process->setTimeout(null);
        $process->run();
    }

    public static function createDatabase(string $databaseName, string $phpBinary, string $projectDir): void
    {
        $process = new Process([
            $phpBinary,
            'bin/console',
            'doctrine:database:create',
            '--connection='.$databaseName,
            '--env='.$_ENV['APP_ENV']
        ], $projectDir);
        $process->setTimeout(null);
        $process->run();

        $process = new Process([
            $phpBinary,
            'bin/console',
            'doctrine:migrations:migrate',
            '--em='.$databaseName,
            '--no-interaction',
            '--env='.$_ENV['APP_ENV']
        ], $projectDir);
        $process->setTimeout(null); // Disable the process timeout
        $process->run();

        $process = new Process([
            $phpBinary,
            'bin/console',
            'doctrine:fixtures:load',
            '--em='.$databaseName,
            '--append',
            '--env='.$_ENV['APP_ENV']
        ], $projectDir);
        $process->setTimeout(null);
        $process->run();
    }
}
