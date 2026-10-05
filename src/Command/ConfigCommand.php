<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\Split\ConfigValues;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A command of the config tools. A command does its work and says what it found out to a ConfigReport: the data of its
 * result, its warnings and its errors. It prints nothing itself. This class turns that into
 *  - a JSON document on stdout, and nothing else (--format=json, for programs), or
 *  - text for people (the default): the document is passed through JSON and back (so that the text is made of exactly
 *    what a program gets) and ConfigTextRenderer writes it.
 *
 * The JSON document:
 *   {"schema": 1, "command": "app:config:merge", "success": true, "exitCode": 0,
 *    "errors": [{"code": "...", "message": "...", "file": "...", "details": {...}}],
 *    "warnings": [{"code": "...", "message": "..."}],
 *    "data": { ...what the command found out... }}
 * The exit code is the one of the process: 0 when it went well, 1 when it found a problem or could not do what was
 * asked, 2 when it was used wrongly.
 */
abstract class ConfigCommand extends Command
{
    /** The version of the JSON document: it changes when something in it is removed or changes its meaning. */
    public const int JSON_SCHEMA = 1;

    protected function addFormatOption(): static
    {
        return $this->addOption(
            'format',
            null,
            InputOption::VALUE_REQUIRED,
            'text (for people) or json (one JSON document on stdout, for programs)',
            'text'
        );
    }

    /**
     * Does the command's work, and says what it found out to the report.
     *
     * @return int the exit code
     */
    abstract protected function perform(InputInterface $input, ConfigReport $report): int;

    /**
     * What a command that prints its result (not a report) writes to stdout in text mode, when it went well. Null:
     * nothing. The data of the report that is only meant for this (the result itself) is not shown in the text.
     */
    protected function rawOutput(InputInterface $input, ConfigReport $report): ?string
    {
        return null;
    }

    /**
     * The data of the report that is the result itself: it is in the JSON document, and it is not part of the text.
     *
     * @return list<string>
     */
    protected function resultKeys(): array
    {
        return [];
    }

    /**
     * Do the messages for people go to stderr? So it is for a command that prints its result on stdout.
     */
    protected function messagesToStderr(): bool
    {
        return false;
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = (string)$input->getOption('format');
        if (!in_array($format, ['text', 'json'], true)) {
            (new SymfonyStyle($input, $output))->getErrorStyle()->error("--format is text or json, not \"$format\".");
            return Command::INVALID;
        }
        $json = $format === 'json';
        $report = new ConfigReport();
        try {
            $exit = $this->perform($input, $report);
        } catch (\Throwable $e) {
            if (!$json && $output->isVerbose()) {
                throw $e; // for people who want to see where it went wrong
            }
            $exit = $report->failThrowable($e);
        }
        $document = [
            'schema' => self::JSON_SCHEMA,
            'command' => $this->getName(),
            'success' => $exit === Command::SUCCESS,
            'exitCode' => $exit,
            'errors' => $report->errors,
            'warnings' => $report->warnings,
            'data' => (object)$report->data,
        ];
        if ($json) {
            $output->write(
                json_encode(
                    $document,
                    (ConfigValues::ENCODE_FLAGS & ~JSON_PRETTY_PRINT) | JSON_INVALID_UTF8_SUBSTITUTE
                ) . "\n",
                false,
                OutputInterface::OUTPUT_RAW
            );
            return $exit;
        }
        $raw = $exit === Command::SUCCESS ? $this->rawOutput($input, $report) : null;
        foreach ($this->resultKeys() as $key) {
            unset($report->data[$key]);
        }
        $document['data'] = $report->data;
        $io = new SymfonyStyle($input, $output);
        if ($this->messagesToStderr()) {
            $io = $io->getErrorStyle();
        }
        // text is made of what a program gets: the document as it is in JSON, not as it was in memory
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(
            (string)json_encode($document, ConfigValues::ENCODE_FLAGS & ~JSON_PRETTY_PRINT),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        (new ConfigTextRenderer())->render((string)$this->getName(), $decoded, $io);
        if ($raw !== null) {
            $output->write($raw, false, OutputInterface::OUTPUT_RAW);
        }
        return $exit;
    }

    /**
     * Says that files were left out of the configs because they are not valid JSON (a file that can not be read may
     * be a config). A command that writes does not go on without them.
     *
     * @param array<string, string> $skipped path => why
     * @return ?int the exit code when the command has to stop, null when it can go on
     */
    protected function skippedFiles(
        ConfigReport $report,
        ConfigDirectory $directory,
        array $skipped,
        bool $writing
    ): ?int {
        if ($skipped === []) {
            return null;
        }
        if ($writing) {
            return $report->failWith(array_map(
                fn(string $path, string $why) => [
                    'code' => 'file_not_json',
                    'message' => $why,
                    'file' => $directory->relativePath($path),
                ],
                array_keys($skipped),
                $skipped
            ));
        }
        foreach ($skipped as $path => $why) {
            $report->warning(
                sprintf('Skipped %s, %s.', $directory->relativePath($path), $why),
                'file_skipped',
                ['path' => $directory->relativePath($path)]
            );
        }
        return null;
    }
}
