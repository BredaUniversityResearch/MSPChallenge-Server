<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\Split\ConfigValues;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A command of the config tools. It can tell what it did to people (text, the default) or to programs (--format=json:
 * one JSON document on stdout, nothing else, also when the command fails).
 *
 * A command only says what it has to say to the report: ConfigReport::warning(), fail() and the data of its result.
 * What is shown, and when, is done here.
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
     * Does the command's work.
     *
     * @param OutputInterface $output what the command writes itself (stdout): for programs it goes nowhere
     * @param SymfonyStyle $io the messages for people: for programs they go nowhere
     * @return int the exit code
     */
    abstract protected function perform(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        ConfigReport $report
    ): int;

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
        $shown = array_map(
            fn(string $path, string $why) => $directory->relativePath($path) . ': ' . $why,
            array_keys($skipped),
            $skipped
        );
        if ($writing) {
            return $report->failWith(
                array_merge(
                    [
                        'Nothing is written, these files are not valid JSON (fix them, or leave them out with '
                        . '--pattern):'
                    ],
                    $shown
                ),
                array_map(
                    fn(string $path, string $why) => [
                        'code' => 'file_not_json',
                        'message' => $why,
                        'file' => $directory->relativePath($path),
                    ],
                    array_keys($skipped),
                    $skipped
                )
            );
        }
        foreach ($skipped as $path => $why) {
            $report->warning(
                sprintf('Skipped %s, %s.', $directory->relativePath($path), $why),
                'file_skipped',
                details: ['path' => $directory->relativePath($path)]
            );
        }
        return null;
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
        // for programs the document is all there is on stdout: what is meant for people goes nowhere
        $sink = $json ? new NullOutput() : $output;
        $io = new SymfonyStyle($input, $sink);
        if (!$json && $this->messagesToStderr()) {
            $io = $io->getErrorStyle();
        }
        $report = new ConfigReport($io, $json);
        try {
            $exit = $this->perform($input, $sink, $io, $report);
        } catch (\Throwable $e) {
            if (!$json) {
                throw $e;
            }
            $exit = $report->failThrowable($e);
        }
        if ($json) {
            $document = [
                'schema' => self::JSON_SCHEMA,
                'command' => $this->getName(),
                'success' => $exit === Command::SUCCESS,
                'exitCode' => $exit,
                'errors' => $report->errors,
                'warnings' => $report->warnings,
                'data' => (object)$report->data,
            ];
            $output->write(
                json_encode(
                    $document,
                    (ConfigValues::ENCODE_FLAGS & ~JSON_PRETTY_PRINT) | JSON_INVALID_UTF8_SUBSTITUTE
                ) . "\n",
                false,
                OutputInterface::OUTPUT_RAW
            );
        }
        return $exit;
    }
}
