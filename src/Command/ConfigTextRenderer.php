<?php

namespace App\Command;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Writes the document of a config command (see ConfigCommand) as text for people.
 *
 * It is a function of the document: it knows nothing else about the command, so everything a person sees is in the
 * document, and so a program has it too. The document is the one that was decoded from JSON, so the renderer can be
 * tested with documents that are written out, or taken from a command run with --format=json.
 *
 * The order: the warnings, what the command found (its report), the errors, and at the end what happened (a note about
 * a dry run, a success).
 */
final class ConfigTextRenderer
{
    /** Errors that people see as a warning: the command found something, it did not fail. */
    private const array FINDINGS = ['check_failed', 'no_configs'];

    /** Warnings of split that are shown in its report, in a list of their own. */
    private const array SPLIT_WARNINGS = ['split_warning', 'same_content', 'several_versions'];

    /**
     * @param array<string, mixed> $document the document of the command, as decoded from JSON
     */
    public function render(string $command, array $document, SymfonyStyle $io): void
    {
        $data = self::arr($document, 'data');
        $warnings = self::arr($document, 'warnings');
        $errors = self::arr($document, 'errors');
        $success = (bool)($document['success'] ?? false);

        $this->warnings($io, $command, $warnings);
        switch ($command) {
            case 'app:config:list':
                $this->list($io, $data);
                break;
            case 'app:config:validate':
                $this->validate($io, $data);
                break;
            case 'app:config:verify':
                $this->verify($io, $data);
                break;
            case 'app:config:strip':
                $this->strip($io, $data);
                break;
            case 'app:config:split':
                $this->split($io, $data, $warnings);
                break;
            // merge has nothing to report
        }
        $this->errors($io, $command, $errors);
        if ($success) {
            $this->closing($io, $command, $data);
        }
    }

    // ------------------------------------------------------------------------------------------ warnings, errors

    /**
     * @param array<mixed> $warnings
     */
    private function warnings(SymfonyStyle $io, string $command, array $warnings): void
    {
        $notStrippable = [];
        foreach ($warnings as $warning) {
            $warning = (array)$warning;
            $code = self::str($warning, 'code');
            $message = self::str($warning, 'message');
            if ($command === 'app:config:split' && in_array($code, self::SPLIT_WARNINGS, true)) {
                continue; // in the report
            }
            if ($code === 'config_not_strippable') {
                $notStrippable[] = $message;
                continue;
            }
            $file = self::str(self::arr($warning, 'details'), 'file');
            $io->warning($file === '' ? $message : $file . ': ' . $message);
        }
        if ($notStrippable !== []) {
            $io->warning(array_merge(
                ['These configs cannot be stripped without changing their meaning, they will be left as they are:'],
                $notStrippable
            ));
        }
    }

    /**
     * @param array<mixed> $errors
     */
    private function errors(SymfonyStyle $io, string $command, array $errors): void
    {
        // a few kinds of errors come in a list that has a line to tell what it is
        $blocks = [];
        $single = [];
        foreach ($errors as $error) {
            $error = (array)$error;
            $code = self::str($error, 'code');
            $file = self::str($error, 'file');
            $line = ($file === '' ? '' : $file . ': ') . self::str($error, 'message');
            if (in_array($code, self::FINDINGS, true)) {
                $single[] = ['warning', self::str($error, 'message')];
            } elseif ($code === 'file_not_json') {
                $blocks['file_not_json'][] = $line;
            } elseif ($code === 'write_failed') {
                $blocks['write_failed'][] = $line;
            } elseif ($code === 'stripped_configs_unsafe') {
                foreach (self::arr(self::arr($error, 'details'), 'configs') as $config) {
                    $blocks['stripped_configs_unsafe'][] = (string)(is_scalar($config) ? $config : '');
                }
            } elseif ($file !== '' && in_array($command, ['app:config:split', 'app:config:strip'], true)) {
                $blocks['invalid_configs'][] = $line;
            } else {
                $single[] = ['error', self::str($error, 'message')];
            }
        }
        $headlines = [
            'invalid_configs' => 'Cannot continue, these configs are invalid:',
            'file_not_json' => 'Nothing is written, these files are not valid JSON (fix them, or leave them out with '
                . '--pattern):',
            'write_failed' => 'Nothing was changed, not everything could be written and verified:',
            'stripped_configs_unsafe' => 'Nothing is written: these stripped configs cannot be written for the new '
                . 'generic config:',
        ];
        foreach ($blocks as $kind => $lines) {
            $io->error(array_merge([$headlines[$kind]], $lines));
        }
        foreach ($single as [$style, $message]) {
            $style === 'warning' ? $io->warning($message) : $io->error($message);
        }
    }

    /**
     * What happened, when the command went well.
     *
     * @param array<string, mixed> $data
     */
    private function closing(SymfonyStyle $io, string $command, array $data): void
    {
        switch ($command) {
            case 'app:config:merge':
                if (self::str($data, 'output') !== '') {
                    $io->success('Wrote ' . self::str($data, 'output'));
                }
                break;
            case 'app:config:verify':
                $results = self::arr($data, 'results');
                if ($results !== []) {
                    $io->success(sprintf('All %d config(s) give the original config.', count($results)));
                }
                break;
            case 'app:config:validate':
                $results = self::arr($data, 'results');
                if ($results !== []) {
                    $io->success(sprintf('All %d config(s) are valid.', count($results)));
                }
                break;
            case 'app:config:strip':
                $this->closingStrip($io, $data);
                break;
            case 'app:config:split':
                $this->closingSplit($io, $data);
                break;
        }
    }

    // ------------------------------------------------------------------------------------------------------ list

    /**
     * @param array<string, mixed> $data
     */
    private function list(SymfonyStyle $io, array $data): void
    {
        if ($data === []) {
            return;
        }
        $io->section('Configs');
        $rows = [];
        foreach (self::arr($data, 'configs') as $config) {
            $config = (array)$config;
            $parent = self::str($config, 'parent');
            $chain = array_map('strval', self::arr($config, 'chain'));
            $rows[] = [
                self::str($config, 'id'),
                self::str($config, 'kind'),
                $parent === '' ? '-' : implode(' > ', $chain === [] ? [$parent . ' (?)'] : $chain),
                self::int($config, 'layers'),
                self::str(self::arr($config, 'problem'), 'message'),
            ];
        }
        if ($rows === []) {
            $io->writeln('None: no config found.');
        } else {
            $io->table(['Config', 'Kind', 'Parents', 'Layers', 'Problem'], $rows);
        }

        $io->section('Generic configs (parents)');
        $rows = [];
        foreach (self::arr($data, 'parents') as $parent) {
            $parent = (array)$parent;
            $rows[] = [
                self::str($parent, 'name'),
                self::str($parent, 'path'),
                self::str($parent, 'parent') ?: '-',
                self::int($parent, 'layers'),
                self::children(array_map('strval', self::arr($parent, 'children'))),
                self::str(self::arr($parent, 'problem'), 'message'),
            ];
        }
        if ($rows === []) {
            $io->writeln('None: no generic config found.');
        } else {
            $io->table(['Name', 'File', 'Parent', 'Layers', 'Used by', 'Problem'], $rows);
        }

        $duplicates = self::arr($data, 'duplicates');
        if ($duplicates !== []) {
            $io->section('Names that two files have');
            $io->listing(array_map(
                static fn($d) => self::str((array)$d, 'name') . ': '
                    . implode(' and ', array_map('strval', self::arr((array)$d, 'paths'))),
                $duplicates
            ));
        }
        $skipped = self::arr($data, 'skipped');
        if ($skipped !== []) {
            $io->section('Files that are not valid JSON');
            $io->listing(array_map(
                static fn($s) => self::str((array)$s, 'path') . ': ' . self::str((array)$s, 'reason'),
                $skipped
            ));
        }
    }

    /**
     * Who uses a parent, in a few words: the first ones, and how many there are.
     *
     * @param string[] $children
     */
    private static function children(array $children): string
    {
        if ($children === []) {
            return '-';
        }
        $shown = array_map(static fn(string $id) => basename($id), array_slice($children, 0, 2));
        return count($children) . ': ' . implode(', ', $shown) . (count($children) > 2 ? ', ...' : '');
    }

    // -------------------------------------------------------------------------------------------------- validate

    /**
     * @param array<string, mixed> $data
     */
    private function validate(SymfonyStyle $io, array $data): void
    {
        $results = self::arr($data, 'results');
        if ($results === []) {
            return;
        }
        $io->table(
            ['Config', 'Kind', 'Result'],
            array_map(
                static function ($result) {
                    $result = (array)$result;
                    return [
                        self::str($result, 'id'),
                        self::str($result, 'kind') ?: '-',
                        ($result['valid'] ?? false) ? 'valid' : count(self::arr($result, 'errors')) . ' problem(s)',
                    ];
                },
                $results
            )
        );
        foreach ($results as $result) {
            $result = (array)$result;
            if ($result['valid'] ?? false) {
                continue;
            }
            $io->section(self::str($result, 'id'));
            $io->listing(array_map(
                static fn($error) => self::str((array)$error, 'message'),
                self::arr($result, 'errors')
            ));
        }
    }

    // ---------------------------------------------------------------------------------------------------- verify

    /**
     * @param array<string, mixed> $data
     */
    private function verify(SymfonyStyle $io, array $data): void
    {
        $results = self::arr($data, 'results');
        if ($results === []) {
            return;
        }
        $io->title('Merged with their parents, compared with ' . self::str($data, 'source'));
        $io->table(
            ['Config', 'Result'],
            array_map(
                static fn($r) => [
                    self::str((array)$r, 'id'),
                    ((array)$r)['same'] ?? false ? 'same as the original' : 'DIFFERENT',
                ],
                $results
            )
        );
        foreach ($results as $result) {
            $result = (array)$result;
            if ($result['same'] ?? false) {
                continue;
            }
            $io->section(self::str($result, 'id'));
            $io->listing(array_map('strval', self::arr($result, 'differences')));
        }
    }

    // ----------------------------------------------------------------------------------------------------- strip

    /**
     * @param array<string, mixed> $data
     */
    private function strip(SymfonyStyle $io, array $data): void
    {
        $results = self::arr($data, 'results');
        if ($results === []) {
            return;
        }
        $labels = [
            'will_be_stripped' => 'will be stripped',
            'already_stripped' => 'already stripped',
            'kept_as_is' => 'kept as is',
        ];
        $counts = ['will_be_stripped' => 0, 'already_stripped' => 0, 'kept_as_is' => 0];
        $rows = [];
        foreach ($results as $result) {
            $result = (array)$result;
            $status = self::str($result, 'status');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $reasons = array_map('strval', self::arr($result, 'reasons'));
            $rows[] = [
                self::str($result, 'id'),
                $labels[$status] ?? $status,
                self::kb(self::int($result, 'nowBytes')),
                $status === 'kept_as_is' ? '-' : self::kb(self::int($result, 'afterBytes')),
                $status === 'kept_as_is' ? ($reasons[0] ?? '') : '',
            ];
        }
        $io->table(['Config', 'Result', 'Now', 'Stripped', 'Why'], $rows);
        $io->writeln(sprintf(
            '%d to strip, %d already stripped, %d kept as is.',
            $counts['will_be_stripped'],
            $counts['already_stripped'],
            $counts['kept_as_is']
        ));
        foreach ($results as $result) {
            $result = (array)$result;
            $reasons = array_map('strval', self::arr($result, 'reasons'));
            if (self::str($result, 'status') === 'kept_as_is' && $reasons !== []) {
                $io->writeln(' - ' . self::str($result, 'id') . ': ' . implode(' ', array_slice($reasons, 0, 4)));
            }
            if ($io->isVerbose()) {
                foreach (self::arr($result, 'warnings') as $warning) {
                    $text = is_scalar($warning) ? (string)$warning : '';
                    $io->writeln(' - ' . self::str($result, 'id') . ': ' . $text);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function closingStrip(SymfonyStyle $io, array $data): void
    {
        $results = self::arr($data, 'results');
        if ($results === []) {
            return;
        }
        $changed = count(array_filter(
            $results,
            static fn($r) => self::str((array)$r, 'status') === 'will_be_stripped'
        ));
        $outputDir = self::str($data, 'outputDir');
        if ($data['check'] ?? false) {
            $io->success('Nothing left to strip.');
        } elseif (!($data['apply'] ?? false) && $outputDir === '') {
            $io->note(
                $changed === 0
                    ? 'Nothing to change.'
                    : 'Dry run, nothing written. Run with --apply to replace the files (each one verified first), '
                        . 'or with --output-dir=DIR to write the result elsewhere.'
            );
        } else {
            $io->success(sprintf(
                '%d config(s) %s%s.',
                self::int($data, 'written'),
                $outputDir === '' ? 'stripped in place' : 'written to ' . $outputDir,
                ' (each verified: merged with its parent it gives the same config)'
            ));
        }
    }

    // ----------------------------------------------------------------------------------------------------- split

    /**
     * @param array<string, mixed> $data
     * @param array<mixed> $warnings
     */
    private function split(SymfonyStyle $io, array $data, array $warnings): void
    {
        $configs = self::arr($data, 'configs');
        if ($configs === []) {
            return;
        }
        $generic = self::arr($data, 'generic');
        $name = self::str($generic, 'name');
        $io->title(sprintf('Splitting %d configs from %s', count($configs), self::str($data, 'root')));
        if (self::int($data, 'strippedCount') > 0) {
            $io->writeln(sprintf(
                '%d of them are stripped already: they are expanded with their parents, so layers that more (or '
                . 'fewer) configs use now can move into (or out of) %s.json.',
                self::int($data, 'strippedCount'),
                $name
            ));
        }

        // file sizes: the files as they are now, and as they would be written (or left alone, when nothing changes)
        $rows = [];
        $larger = false; // does a config get larger?
        foreach ($configs as $config) {
            $config = (array)$config;
            $now = self::int($config, 'nowBytes');
            $after = self::int($config, 'afterBytes');
            $kept = self::str($config, 'status') === 'kept_as_is';
            $larger = $larger || (!$kept && $after > $now);
            $rows[] = [
                self::str($config, 'id'),
                self::int($config, 'layers'),
                self::kb($now),
                $kept ? 'cannot be stripped' : self::kb($after),
                $kept ? '-' : self::win($now, $after),
            ];
        }
        $io->section('Configs (file sizes now, and as they would be written)');
        $io->table(['Config', 'Layers', 'Now', 'After', 'Win'], $rows);
        $genericNow = $generic['nowBytes'] ?? null;
        $genericAfter = self::int($generic, 'afterBytes');
        $io->writeln(
            $genericNow === null
                ? sprintf('%s.json: %s (new)', $name, self::kb($genericAfter))
                : sprintf(
                    '%s.json: %s now, %s after%s',
                    $name,
                    self::kb((int)$genericNow),
                    self::kb($genericAfter),
                    ($generic['changed'] ?? false) ? '' : ' (unchanged)'
                )
        );
        $total = self::arr($data, 'total');
        $totalNow = self::int($total, 'nowBytes');
        $totalAfter = self::int($total, 'afterBytes');
        $io->writeln(sprintf(
            'All files, with %s.json: %s now, %s after (%s)',
            $name,
            self::kb($totalNow),
            self::kb($totalAfter),
            $totalAfter <= $totalNow
                ? self::win($totalNow, $totalAfter) . ' smaller'
                : sprintf('%.1f%% larger', 100 * ($totalAfter - $totalNow) / max(1, $totalNow))
        ));
        if ($larger) {
            $io->writeln(
                'A negative win: that config needs more overrides afterwards, because what most configs share '
                . '(the generic config) changed.'
            );
        }

        $layers = self::arr($data, 'layers');
        $io->section('Layers');
        $io->writeln(sprintf(
            '%d generic layers (each used by 2 or more configs); %d layers are used by one config only and stay in '
            . 'its file. Region layer entries overriding a generic value, per field:',
            self::int($layers, 'generic'),
            self::int($layers, 'regionOnly')
        ));
        $overrides = self::arr($layers, 'overrides');
        $io->writeln('  ' . ($overrides === [] ? 'none' : implode(', ', array_map(
            static fn($field, $count) => "$field: " . (is_scalar($count) ? $count : ''),
            array_keys($overrides),
            $overrides
        ))));
        $io->writeln(sprintf(
            '%d layer names were not in layer_names of %s.json and got a proposed generic name.',
            self::int($layers, 'proposedNames'),
            $name
        ));
        if ($generic['existed'] ?? false) {
            $this->changesToGeneric($io, $name, $layers);
        }

        $io->section('Sections: how much is actually shared between the configs');
        $rows = [];
        foreach (self::arr($data, 'sections') as $section) {
            $section = (array)$section;
            $rows[] = [
                self::str($section, 'name'),
                self::int($section, 'participating') . ' configs',
                self::str($section, 'kind') === 'list'
                    ? sprintf(
                        '%d of %d distinct items are generic (shared)',
                        self::int($section, 'genericItems'),
                        self::int($section, 'distinctItems')
                    )
                    : sprintf(
                        '%d of %d values shared by 2+ configs',
                        self::int($section, 'genericValues'),
                        self::int($section, 'values')
                    ),
            ];
        }
        $io->table(['Section', 'Participating', 'Generic result'], $rows);

        $removed = self::arr($data, 'removed');
        if ($removed !== []) {
            $io->section('Removed / rewritten on purpose (design document)');
            $io->table(['What', 'Count'], array_map(
                static fn($what, $count) => [$what, is_scalar($count) ? $count : ''],
                array_keys($removed),
                $removed
            ));
        }
        $listed = [];
        foreach ($warnings as $warning) {
            $warning = (array)$warning;
            if (in_array(self::str($warning, 'code'), self::SPLIT_WARNINGS, true)) {
                $listed[] = self::str($warning, 'message');
            }
        }
        if ($listed !== []) {
            $io->section('Warnings');
            $io->listing($listed);
        }
        $nameMap = self::arr($layers, 'nameMap');
        if ($io->isVerbose() && self::int($layers, 'proposedNames') > 0) {
            $io->section('Proposed generic names (review these)');
            $rows = [];
            foreach ($nameMap as $generic => $names) {
                $rows[] = [$generic, implode(', ', array_map('strval', (array)$names))];
            }
            $io->table(['Generic name', 'Layer names'], $rows);
        }
    }

    /**
     * @param array<string, mixed> $layers
     */
    private function changesToGeneric(SymfonyStyle $io, string $name, array $layers): void
    {
        $io->section("Changes to $name.json");
        $map = self::arr($layers, 'nameMap');
        $describe = static function (array $names) use ($io, $map): string {
            $shown = $io->isVerbose() ? $names : array_slice($names, 0, 8);
            return implode(', ', array_map(
                static function ($layer) use ($map) {
                    $layer = (string)$layer;
                    $layerNames = array_map('strval', self::arr($map, $layer));
                    // no longer in the name map, for example renamed there
                    return $layer . ($layerNames === [] ? '' : ' (' . implode(', ', $layerNames) . ')');
                },
                $shown
            )) . (!$io->isVerbose() && count($names) > 8 ? ', ... (-v lists all)' : '');
        };
        $promoted = self::arr($layers, 'promoted');
        $demoted = self::arr($layers, 'demoted');
        $io->writeln(
            $promoted === []
                ? 'No layer becomes generic.'
                : sprintf(
                    '%d layer(s) become generic, because 2 or more configs use them now: %s',
                    count($promoted),
                    $describe($promoted)
                )
        );
        $io->writeln(
            $demoted === []
                ? 'No layer moves back to a single config.'
                : sprintf(
                    '%d layer(s) move back into the one config that uses them: %s',
                    count($demoted),
                    $describe($demoted)
                )
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function closingSplit(SymfonyStyle $io, array $data): void
    {
        $configs = self::arr($data, 'configs');
        if ($configs === []) {
            return;
        }
        $name = self::str(self::arr($data, 'generic'), 'name');
        $outputDir = self::str($data, 'outputDir');
        if ($data['check'] ?? false) {
            $io->success('Nothing to re-split.');
            return;
        }
        $written = self::arr($data, 'written');
        if ($written === []) {
            if (!($data['apply'] ?? false) && $outputDir === '') {
                $io->note(
                    "Dry run, nothing written. Run with --apply to write $name.json and to replace the configs "
                    . 'by their stripped versions (each one verified first), or with --output-dir=DIR to write them to '
                    . 'another directory.'
                );
            }
            return;
        }
        $shown = self::str($written, 'genericPath');
        if ($written['nothingToWrite'] ?? false) {
            $unchanged = count(array_filter(
                $configs,
                static fn($c) => self::str((array)$c, 'status') === 'already_stripped'
            ));
            $io->success(sprintf(
                'Nothing to write: %s and the %d stripped config(s) are what a split gives already.',
                $shown,
                $unchanged
            ));
            return;
        }
        $genericWritten = (bool)($written['generic'] ?? false);
        $io->success(sprintf(
            'Wrote %s%d stripped config(s)%s%s.',
            $genericWritten ? $shown . ' and ' : '',
            self::int($written, 'configs'),
            $outputDir === '' ? ' (replacing the existing ones, each verified)' : ' to ' . $outputDir,
            $genericWritten ? '' : '; ' . $shown . ' is unchanged'
        ));
    }

    // ----------------------------------------------------------------------------------------------------- helpers

    /**
     * Always in kb, so that sizes can be compared at a glance (and with what a file manager says).
     */
    private static function kb(int $bytes): string
    {
        return number_format($bytes / 1024, 1, '.', '') . ' kb';
    }

    /**
     * How much smaller a file gets, as a percentage of its size now (negative: it gets larger).
     */
    private static function win(int $now, int $after): string
    {
        return $now === 0 ? '-' : sprintf('%.1f%%', 100 * ($now - $after) / $now);
    }

    /**
     * @param array<mixed> $from
     * @return array<mixed>
     */
    private static function arr(array $from, string $key): array
    {
        $value = $from[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<mixed> $from
     */
    private static function str(array $from, string $key): string
    {
        $value = $from[$key] ?? '';
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @param array<mixed> $from
     */
    private static function int(array $from, string $key): int
    {
        $value = $from[$key] ?? 0;
        return is_numeric($value) ? (int)$value : 0;
    }
}
