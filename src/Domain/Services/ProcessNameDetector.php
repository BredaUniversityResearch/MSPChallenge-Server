<?php

namespace App\Domain\Services;

class ProcessNameDetector
{
    public const PROCESS_NAME_MAX_LENGTH = 128;

    /**
     * Intelligently detects the current process name from various sources
     * Priority:
     * 1. DB_PROCESS_NAME environment variable
     * 2. Supervisor process name (if running under supervisor)
     * 3. PHP SAPI (cli, fpm-fcgi, etc.)
     * 4. Command line argument (for named workers)
     * 5. Default fallback
     */
    public static function getProcessName(?string $defaultName = null): ?string
    {
        // 1. Check explicit DB_PROCESS_NAME env var (check multiple sources for flexibility)
        if ($processName = $_ENV['DB_PROCESS_NAME'] ?? $_SERVER['DB_PROCESS_NAME'] ?? getenv('DB_PROCESS_NAME')) {
            return $processName;
        }

        // 2. Try to get supervisor process name (for messenger workers, etc.)
        if (getenv('DOCKER') !== false) {
            if ($supervisorProcessName = self::getSupervisorProcessName()) {
                return $supervisorProcessName;
            }
        }

        // 3. Detect from SAPI and context
        $sapi = php_sapi_name();

        if ($sapi === 'cli' || $sapi === 'cli-server') {
            // Skip CLI flags (e.g. --ansi/-v) and pick the first meaningful token.
            $command = self::getCliCommandToken($_SERVER['argv'] ?? []);
            if ($command !== null) {
                // messenger:consume -> messenger
                // bin/chat-server.php -> chat_server
                // bin/console watchdog:listen -> watchdog_listen
                if (strpos($command, 'messenger:') === 0) {
                    return 'messenger_' . str_replace(':', '_', $command);
                }
                if (strpos($command, ':') !== false) {
                    return str_replace(':', '_', $command);
                }
                // Extract script name
                $scriptName = basename($command, '.php');
                return $scriptName;
            }
            return 'cli';
        }

        if ($sapi === 'fpm-fcgi') {
            return 'php_fpm';
        }

        // FrankenPHP appears as 'cli' when running workers, but check for FrankenPHP-specific markers
        if (function_exists('frankenphp_request_context')) {
            $context = @frankenphp_request_context();
            if ($context && isset($context['request_handler'])) {
                return 'frankenphp_web';
            }
            return 'frankenphp';
        }

        return $defaultName ?? 'php_unknown';
    }

    private static function getCliCommandToken(array $argv): ?string
    {
        foreach ($argv as $index => $arg) {
            if ($index === 0 || !is_string($arg) || $arg === '') {
                continue;
            }
            if ($arg[0] === '-') {
                continue;
            }
            return $arg;
        }

        // Fallback: direct script execution (e.g. php bin/chat-server.php)
        $script = $argv[0] ?? null;
        if (is_string($script) && $script !== '' && str_ends_with($script, '.php')) {
            return $script;
        }

        return null;
    }

    /**
     * Collects raw inputs used for process-name detection.
     */
    public static function collectProcessNameInputs(): array
    {
        $argv = $_SERVER['argv'] ?? [];

        return [
            'env' => $_ENV['DB_PROCESS_NAME'] ?? null,
            'srv' => $_SERVER['DB_PROCESS_NAME'] ?? null,
            'get' => getenv('DB_PROCESS_NAME') ?: null,
            'sup' => self::getSupervisorProcessName(),
            'sap' => php_sapi_name(),
            'a0' => $argv[0] ?? null,
            'a1' => $argv[1] ?? null,
            'a2' => $argv[2] ?? null,
        ];
    }

    /**
     * Builds a compact debug process-name string from detection inputs.
     */
    public static function buildDebugProcessName(int $maxLength = self::PROCESS_NAME_MAX_LENGTH): string
    {
        $pairs = [];
        foreach (self::collectProcessNameInputs() as $key => $value) {
            $safeValue = self::sanitizeDebugValue((string) ($value ?? '-'));
            $pairs[] = $key . ':' . $safeValue;
        }

        $debugName = 'dbg|' . implode('|', $pairs);
        return substr($debugName, 0, max(1, $maxLength));
    }

    /**
     * Gets the supervisor process name if running under supervisor
     */
    private static function getSupervisorProcessName(): ?string
    {
        try {
            $pid = getmypid();
            if (!$pid) {
                return null;
            }

            // Query supervisorctl and match the exact PID to avoid accidental multi-line substring matches.
            $output = @shell_exec('supervisorctl status 2>/dev/null');
            if (!$output) {
                return null;
            }

            $targetPid = (string) $pid;
            $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (!preg_match('/^(\S+)\s+\w+\s+pid\s+(\d+),/i', $line, $matches)) {
                    continue;
                }

                if ($matches[2] !== $targetPid) {
                    continue;
                }

                return $matches[1];
            }
        } catch (\Throwable $e) {
            // Silently fail if supervisorctl is not available
        }

        return null;
    }

    private static function sanitizeDebugValue(string $value): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9:_\/.\-]/', '_', $value);
    }
}
