<?php

namespace App\Composer;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Helpers invoked from composer.json's "scripts" section.
 *
 * Composer executes plain string script commands through the native shell of
 * the OS that PHP/Composer itself runs on (cmd.exe on Windows), regardless of
 * which terminal (e.g. Git Bash) was used to invoke `composer install/update`.
 * POSIX-only syntax such as `VAR=value command`, `(... || true)` subshells, or
 * relying on a `true` builtin is therefore not portable to native Windows.
 *
 * Using a PHP static-method callback instead avoids the shell entirely and
 * works identically on every platform.
 */
class ScriptHandler
{
    /**
     * Cross-platform equivalent of the previous composer.json script:
     *   (DB_PROCESS_NAME='doctrine_clear_metadata' php -d memory_limit=1G bin/console doctrine:cache:clear-metadata -n || true)
     */
    public static function clearDoctrineMetadataCache(): void
    {
        $phpBinary = (new PhpExecutableFinder())->find() ?: 'php';

        $process = new Process(
            [$phpBinary, '-d', 'memory_limit=1G', 'bin/console', 'doctrine:cache:clear-metadata', '-n'],
            null,
            ['DB_PROCESS_NAME' => 'doctrine_clear_metadata']
        );
        $process->setTimeout(null);

        try {
            $process->run(function (string $type, string $buffer): void {
                echo $buffer;
            });
        } catch (\Throwable $e) {
            // Best-effort step only (matches the previous "|| true" behaviour):
            // never let a failure here break composer install/update, e.g. when
            // the database isn't reachable yet during a fresh setup.
        }
    }
}

