<?php

namespace App\Command;

use App\Domain\Config\ConfigParentException;
use App\Domain\Config\InvalidSessionConfigException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What a config command has to tell, for people (printed at once) and for programs (kept, and printed as JSON at the
 * end when --format=json is used): the errors, the warnings, and the data of the result.
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
     * @param bool $json is the result printed as JSON (a command can leave out what only people need)
     */
    public function __construct(private readonly SymfonyStyle $io, public readonly bool $json = false)
    {
    }

    /**
     * A warning: the command goes on.
     *
     * @param string|string[] $text what people see (a list is shown as one block)
     * @param ?string[] $messages what is kept for programs, one warning each (default: the text)
     * @param array<string, mixed> $details
     */
    public function warning(
        string|array $text,
        string $code = 'warning',
        ?array $messages = null,
        array $details = []
    ): void {
        $this->io->warning($text);
        foreach ($messages ?? (array)$text as $message) {
            $this->warnings[] = ['code' => $code, 'message' => $message]
                + ($details === [] ? [] : ['details' => $details]);
        }
    }

    /**
     * Keeps warnings for programs that the command shows itself (people see them in its own way).
     *
     * @param string[] $messages
     * @param array<string, mixed> $details
     */
    public function recordWarnings(array $messages, string $code = 'warning', array $details = []): void
    {
        foreach ($messages as $message) {
            $this->warnings[] = ['code' => $code, 'message' => $message]
                + ($details === [] ? [] : ['details' => $details]);
        }
    }

    /**
     * The command stops.
     *
     * @param string|string[] $text what people see
     * @param array<string, mixed> $details
     * @return int the exit code
     */
    public function fail(
        string|array $text,
        string $code = 'error',
        int $exit = Command::FAILURE,
        array $details = [],
        ?string $file = null
    ): int {
        $this->io->error($text);
        $this->errors[] = ['code' => $code, 'message' => implode(' ', (array)$text)]
            + ($file === null ? [] : ['file' => $file])
            + ($details === [] ? [] : ['details' => $details]);
        return $exit;
    }

    /**
     * The command found something that makes it end with an exit code of 1, but that is not a failure of the command
     * itself: it is shown as a warning (a check that finds work to do, for example).
     *
     * @param string|string[] $text what people see
     * @param array<string, mixed> $details
     * @return int the exit code
     */
    public function finding(string|array $text, string $code, array $details = []): int
    {
        $this->io->warning($text);
        $this->errors[] = ['code' => $code, 'message' => implode(' ', (array)$text)]
            + ($details === [] ? [] : ['details' => $details]);
        return Command::FAILURE;
    }

    /**
     * The command stops, for several reasons that are told one by one (to programs).
     *
     * @param string|string[] $text what people see
     * @param list<array<string, mixed>> $errors each with at least a code and a message
     * @return int the exit code
     */
    public function failWith(string|array $text, array $errors, int $exit = Command::FAILURE): int
    {
        $this->io->error($text);
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
        $error = self::describe($e, $file);
        $this->io->error($error['message']);
        $this->errors[] = $error;
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
