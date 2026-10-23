<?php

namespace App\Command;

use App\Domain\Config\ConfigParentException;
use App\Domain\Config\InvalidSessionConfigException;
use Symfony\Component\Console\Command\Command;

/**
 * What a config command has found out: the data of its result, its warnings and its errors. A command prints nothing
 * itself: ConfigCommand turns this into the JSON document (--format=json) or into text (ConfigTextRenderer).
 *
 * Every error and warning has a code that programs can rely on (see ConfigReport::describe() and
 * docs/config-tools-cli.md); the message is for people and can change.
 */
final class ConfigReport
{
    /** @var list<array<string, mixed>> */
    public array $errors = [];
    /** @var list<array<string, mixed>> */
    public array $warnings = [];
    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * A warning: the command goes on.
     *
     * @param array<string, mixed> $details
     */
    public function warning(string $message, string $code = 'warning', array $details = []): void
    {
        $this->warnings[] = ['code' => $code, 'message' => $message] + ($details === [] ? [] : ['details' => $details]);
    }

    /**
     * The command stops.
     *
     * @param array<string, mixed> $details
     * @return int the exit code
     */
    public function fail(
        string $message,
        string $code = 'error',
        int $exit = Command::FAILURE,
        array $details = [],
        ?string $file = null
    ): int {
        $this->errors[] = ['code' => $code, 'message' => $message]
            + ($file === null ? [] : ['file' => $file])
            + ($details === [] ? [] : ['details' => $details]);
        return $exit;
    }

    /**
     * The command found something that makes it end with an exit code of 1, but that is not a failure of the command
     * itself: a check that finds work to do, or a tool that finds nothing to work on. It is an error in the document,
     * and people see it as a warning.
     *
     * @param array<string, mixed> $details
     * @return int the exit code
     */
    public function finding(string $message, string $code, array $details = []): int
    {
        return $this->fail($message, $code, Command::FAILURE, $details);
    }

    /**
     * The command stops, for several reasons that are told one by one.
     *
     * @param list<array<string, mixed>> $errors each with at least a code and a message
     * @return int the exit code
     */
    public function failWith(array $errors, int $exit = Command::FAILURE): int
    {
        array_push($this->errors, ...$errors);
        return $exit;
    }

    /**
     * The command stops because of something that was thrown.
     *
     * @return int the exit code
     */
    public function failThrowable(\Throwable $e, ?string $file = null, int $exit = Command::FAILURE): int
    {
        $this->errors[] = self::describe($e, $file);
        return $exit;
    }

    /**
     * What a program needs to know about something that was thrown: a code, the message, and the details.
     *
     * @return array<string, mixed>
     */
    public static function describe(\Throwable $e, ?string $file = null): array
    {
        $file = $file === null ? [] : ['file' => $file];
        if ($e instanceof ConfigParentException) {
            return ['code' => $e->reason, 'message' => $e->getMessage()] + $file
                + ($e->details === [] ? [] : ['details' => $e->details]);
        }
        if ($e instanceof InvalidSessionConfigException) {
            $errors = $e->getErrors();
            $syntax = str_starts_with((string)($errors[0] ?? ''), 'Invalid JSON');
            return ['code' => $syntax ? 'invalid_json' : 'invalid_config', 'message' => $e->summary()] + $file
                + ['details' => ['errors' => $errors]];
        }
        if ($e instanceof \JsonException) {
            return ['code' => 'invalid_json', 'message' => $e->getMessage()] + $file;
        }
        $message = $e->getMessage();
        $code = match (true) {
            str_starts_with($message, 'File not found') => 'file_not_found',
            str_starts_with($message, 'Cannot read') => 'file_unreadable',
            str_contains($message, 'does not contain a JSON object') => 'invalid_json',
            default => 'error',
        };
        return ['code' => $code, 'message' => (string)strtok($message, "\n")] + $file;
    }
}
