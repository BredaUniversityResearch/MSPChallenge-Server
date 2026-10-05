<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
use App\Domain\Config\InvalidSessionConfigException;
use App\Domain\Config\Merge\ConfigFileStripper;
use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Merge\StripPlan;
use App\Domain\Config\SessionConfigValidator;
use App\Domain\Config\Split\ConfigSplitter;
use App\Domain\Config\Split\ConfigValues;
use App\Domain\Config\Split\GenericNameRegistry;
use App\Domain\Config\Split\SplitResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'app:config:split',
    description: 'Derives a generic config (--generic) from the configs in ServerManager/configfiles (complete ones, '
        . 'or ones that are stripped already: those are expanded with their parents first) and strips every config '
        . 'down to what is specific for its region, with the generic config as its parent. Reports only, unless '
        . '--apply or --output-dir is given.'
)]
final class ConfigSplitCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly SessionConfigValidator $validator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Config root (absolute, or relative to the project dir). The configs and the generic configs can be '
                . 'anywhere below it',
                'ServerManager/configfiles'
            )
            ->addOption(
                'pattern',
                null,
                InputOption::VALUE_REQUIRED,
                'File name pattern of the configs, anywhere below --dir',
                '*.json'
            )
            ->addOption(
                'generic',
                null,
                InputOption::VALUE_REQUIRED,
                'The name of the generic config to create, a file <name>.json in --dir. The configs get it as their '
                . 'parent (metadata.parent). It holds the names of the layers too (layer_names): edit them to merge or '
                . 'split generic layers',
                ConfigDirectory::DEFAULT_GENERIC
            )
            ->addOption(
                'skip-validation',
                null,
                InputOption::VALUE_NONE,
                'Do not validate the input configs against SessionConfigJSONSchema.json'
            )
            ->addOption(
                'apply',
                null,
                InputOption::VALUE_NONE,
                'Write the generic config, and replace every config by its stripped version. Each one is written '
                . 'to a temporary file, read back and verified (merged with the generic config it must give the '
                . 'original) before it replaces the config'
            )
            ->addOption(
                'output-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Write the generic config and the stripped configs (<folder>/<name>.json) to this directory '
                . 'instead, and leave --dir alone. Handy to compare the result before applying it'
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Write nothing, exit with status 1 when running with --apply would change anything: the generic '
                . 'config (for example layers that a second config now uses, or that only one config uses any '
                . 'more) or a config'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Overwrite the generic config if it exists (this loses changes made to it by hand)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $root = Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir);
        $outputDir = $input->getOption('output-dir');
        $outputRoot = $outputDir === null ? $root : Path::makeAbsolute((string)$outputDir, $this->projectDir);
        $check = (bool)$input->getOption('check');
        $writing = $input->getOption('apply') || $outputDir !== null;
        if ($check && $writing) {
            $io->error('Use --check on its own, it never writes.');
            return Command::INVALID;
        }
        if (!is_dir($root)) {
            $io->error("Config directory not found: $root");
            return Command::FAILURE;
        }
        $directory = new ConfigDirectory($root);
        $genericName = (string)$input->getOption('generic');
        if (!ConfigDirectory::isValidParentName($genericName)) {
            $io->error('--generic is the name of a file without the extension: letters, digits, _ and - only.');
            return Command::INVALID;
        }

        // 1. read the configs. A config that is stripped already is expanded with its parents
        $oldGeneric = null;
        $genericFile = null; // where the generic config is now, if there is one
        try {
            $genericFile = $directory->locateParent($genericName);
            $oldGeneric = $genericFile === null ? null : $directory->read($genericFile);
            if ($oldGeneric !== null && ConfigParents::parentOf($oldGeneric) !== null) {
                $io->error(
                    "$genericName.json has a parent itself, and the command makes a generic config without one. "
                    . 'Choose another name with --generic.'
                );
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $io->warning("Ignoring $genericName.json, it cannot be used: " . $e->getMessage());
        }
        $parents = ConfigParents::fromDirectory($directory);
        $merger = new RegionConfigMerger();
        $configs = []; // what is split: the complete configs, and the expanded stripped ones
        $currents = []; // the files as they are now
        $effectives = []; // the expanded stripped ones, and the complete ones that are in the new shape
        $strippedCount = 0; // how many of those really are stripped
        $paths = [];
        $errors = [];
        $skipped = [];
        $found = $directory->configFiles((string)$input->getOption('pattern'), $skipped);
        if ($genericFile !== null) {
            unset($skipped[$genericFile]); // the generic config is told about by itself when it can not be used
        }
        if ($skipped !== [] && $writing) {
            // a file that can not be read may be a config: do not split without it
            $io->error(array_merge(
                ['Nothing is written, these files are not valid JSON (fix them, or leave them out with --pattern):'],
                array_map(
                    fn(string $path, string $why) => $directory->relativePath($path) . ': ' . $why,
                    array_keys($skipped),
                    $skipped
                )
            ));
            return Command::FAILURE;
        }
        foreach ($skipped as $path => $why) {
            $io->warning(sprintf('Skipped %s, %s.', $directory->relativePath($path), $why));
        }
        foreach ($found as $id => $path) {
            try {
                $config = $directory->read($path);
                if (RegionConfigMerger::isRegionFormat($config)) {
                    $strippedCount += RegionConfigMerger::isStripped($config) ? 1 : 0;
                    $pool = $parents->poolOf($config, 'it'); // fails when a parent is missing
                    $configs[$id] = $effectives[$id] = $merger->merge($pool, $config);
                } else {
                    if (!$input->getOption('skip-validation')) {
                        // the schema describes the final config: validate the config in that shape
                        $this->validator->validate(RegionConfigMerger::toSimulationSettings($config));
                    }
                    $configs[$id] = $config;
                }
                $currents[$id] = $config;
                $paths[$id] = $path;
            } catch (\Throwable $e) {
                $errors[] = sprintf(
                    '%s: %s',
                    $id,
                    $e instanceof InvalidSessionConfigException ? $e->summary() : strtok($e->getMessage(), "\n")
                );
            }
        }
        if ($errors !== []) {
            $io->error(array_merge(['Cannot continue, these configs are invalid:'], $errors));
            return Command::FAILURE;
        }
        if (count($configs) < 2) {
            $io->warning(sprintf(
                'Found %d config(s) in %s; at least 2 are needed to find anything generic.',
                count($configs),
                $root
            ));
            if ($configs === []) {
                return Command::FAILURE;
            }
        }
        $io->title(sprintf('Splitting %d configs from %s', count($configs), $root));
        if ($strippedCount > 0) {
            $io->writeln(sprintf(
                '%d of them are stripped already: they are expanded with their parents, so layers that more (or '
                . 'fewer) configs use now can move into (or out of) %s.json.',
                $strippedCount,
                $genericName
            ));
        }

        // 2. generic names (those in the generic config first, proposals for the rest), the generic config, and the
        // stripped configs
        try {
            $registry = GenericNameRegistry::fromMap($oldGeneric === null ? [] : ConfigParents::nameMapOf($oldGeneric));
            $registry->register($configs);
            $result = new ConfigSplitter($registry)->split($configs);
            $stripper = new ConfigFileStripper();
            $plans = [];
            foreach ($configs as $id => $config) {
                $plans[$id] = $stripper->plan(
                    $id,
                    $paths[$id],
                    $currents[$id],
                    $result->generic,
                    $registry,
                    $effectives[$id] ?? null,
                    $genericName
                );
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        $layerNames = static fn(?\stdClass $generic): array => array_map(
            static fn(\stdClass $layer) => $layer->msp_config_generic_name,
            $generic === null ? [] : ($generic->datamodel->meta ?? [])
        );
        $newNames = $layerNames($result->generic);
        $oldNames = $layerNames($oldGeneric);
        $promoted = $oldGeneric === null ? [] : array_values(array_diff($newNames, $oldNames));
        $demoted = $oldGeneric === null ? [] : array_values(array_diff($oldNames, $newNames));
        $genericChanged = $oldGeneric === null
            || ConfigValues::canonical($oldGeneric) !== ConfigValues::canonical($result->generic);
        $changed = array_filter($plans, static fn(StripPlan $plan) => $plan->status === StripPlan::CHANGED);

        // 3. report
        $this->report(
            $io,
            $result,
            $registry,
            $configs,
            $plans,
            $genericName,
            $oldGeneric !== null,
            $promoted,
            $demoted,
            $oldGeneric === null ? null : (int)@filesize((string)$genericFile),
            $genericChanged
        );
        $refused = array_filter($plans, static fn(StripPlan $plan) => $plan->status === StripPlan::REFUSED);
        if ($refused !== []) {
            $io->warning(array_merge(
                ['These configs cannot be stripped without changing their meaning, they will be left as they are:'],
                array_map(
                    static fn(StripPlan $plan) => $plan->id . ': ' . implode(' ', array_slice($plan->reasons, 0, 3)),
                    $refused
                )
            ));
        }

        // 4. check, or write
        if ($check) {
            if ($genericChanged || $changed !== []) {
                $io->warning(sprintf(
                    'Running with --apply would change %s and %d config(s).',
                    $genericChanged ? "$genericName.json" : "nothing in $genericName.json",
                    count($changed)
                ));
                return Command::FAILURE;
            }
            $io->success('Nothing to re-split.');
            return Command::SUCCESS;
        }
        if (!$writing) {
            $io->note(
                "Dry run, nothing written. Run with --apply to write $genericName.json and to replace the configs "
                . 'by their stripped versions (each one verified first), or with --output-dir=DIR to write them to '
                . 'another directory.'
            );
            return Command::SUCCESS;
        }
        $unsafe = array_filter(
            $refused,
            static fn(StripPlan $plan) => $outputDir === null && isset($effectives[$plan->id])
        );
        if ($unsafe !== []) {
            $io->error(array_merge(
                ['Nothing is written: these stripped configs cannot be written for the new generic config:'],
                array_map(static fn(StripPlan $plan) => $plan->id, $unsafe)
            ));
            return Command::FAILURE;
        }
        // an existing generic config is written where it is (also when it is deeper in the tree); a new one goes in the
        // root of where is written. Another folder to write to gets a flat copy.
        $genericPath = ($outputDir === null ? $genericFile : null) ?? $outputRoot . '/' . $genericName . '.json';
        if (is_file($genericPath) && !$input->getOption('force')) {
            $io->error(
                ConfigDirectory::displayPath($genericPath, $this->projectDir)
                . ' exists (it may have been edited by hand), '
                . 'use --force to overwrite it.'
            );
            return Command::FAILURE;
        }
        $outputDirectory = new ConfigDirectory($outputRoot);
        $staged = [];
        $problems = [];
        try {
            // everything is written to temporary files and verified first; only when all of it is fine the files
            // are replaced, so a problem never leaves stripped configs behind that do not fit their parent
            foreach ($plans as $id => $plan) {
                $skip = $plan->status === StripPlan::REFUSED
                    || ($plan->status === StripPlan::UNCHANGED && $outputDir === null);
                if ($skip) {
                    continue;
                }
                $target = $outputDir === null ? $plan->path : $outputRoot . '/' . $id . '.json';
                [$found, $temporary] = $stripper->stage($plan, $outputDirectory, $result->generic, $target, true);
                foreach ($found as $problem) {
                    $problems[] = "$id: $problem";
                }
                if ($temporary !== null) {
                    $staged[] = [$temporary, $target];
                }
            }
            if ($problems !== []) {
                $outputDirectory->discard(...array_column($staged, 0));
                $io->error(array_merge(
                    ['Nothing was changed, not everything could be written and verified:'],
                    $problems
                ));
                return Command::FAILURE;
            }
            if ($outputDir !== null || $genericChanged) {
                $outputDirectory->write($genericPath, $result->generic);
            }
            foreach ($staged as [$temporary, $target]) {
                $outputDirectory->commit($temporary, $target);
            }
        } catch (\Throwable $e) {
            $outputDirectory->discard(...array_column($staged, 0));
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        $genericShown = ConfigDirectory::displayPath($genericPath, $this->projectDir);
        $genericWritten = $outputDir !== null || $genericChanged;
        if (!$genericWritten && $staged === []) {
            // a split of what is split already gives the same: only files that would change are written
            $io->success(sprintf(
                'Nothing to write: %s and the %d stripped config(s) are what a split gives already.',
                $genericShown,
                count(array_filter($plans, static fn(StripPlan $plan) => $plan->status === StripPlan::UNCHANGED))
            ));
            return Command::SUCCESS;
        }
        $io->success(sprintf(
            'Wrote %s%d stripped config(s)%s%s.',
            $genericWritten ? $genericShown . ' and ' : '',
            count($staged),
            $outputDir === null ? ' (replacing the existing ones, each verified)' : ' to ' . $outputRoot,
            $genericWritten ? '' : '; ' . $genericShown . ' is unchanged'
        ));
        return Command::SUCCESS;
    }

    /**
     * Every config counts as a region when it is decided what is shared. Copies of one config, and versions of it,
     * are no separate regions: they make their data look more shared than it is, and shift what is generic towards
     * them. Say so, so that they can be left out (--pattern).
     *
     * @param array<string, \stdClass> $configs
     * @return string[]
     */
    private function sameRegionWarnings(array $configs): array
    {
        $warnings = [];
        $identical = [];
        foreach ($configs as $id => $config) {
            $identical[md5(ConfigValues::canonical($config->datamodel ?? new \stdClass()))][] = $id;
        }
        foreach ($identical as $ids) {
            if (count($ids) > 1) {
                $warnings[] = sprintf(
                    'These configs have the same content: %s. They count as separate regions, so what they have is '
                    . 'shared by definition. Leave out the copies (--pattern) to see what is really shared.',
                    implode(', ', $ids)
                );
            }
        }
        $versions = [];
        foreach (array_keys($configs) as $id) {
            $versions[dirname((string)$id)][] = basename((string)$id);
        }
        foreach ($versions as $folder => $names) {
            if (count($names) > 1) {
                $warnings[] = sprintf(
                    '%s has %d versions (%s). Each version counts as a region of its own.',
                    $folder,
                    count($names),
                    implode(', ', $names)
                );
            }
        }
        return $warnings;
    }

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
     * @param array<string, \stdClass> $configs
     * @param array<string, StripPlan> $plans
     */
    private function report(
        SymfonyStyle $io,
        SplitResult $result,
        GenericNameRegistry $registry,
        array $configs,
        array $plans,
        string $genericName,
        bool $hadGeneric,
        array $promoted,
        array $demoted,
        ?int $genericNow,
        bool $genericChanged
    ): void {
        // file sizes: the files as they are now, and as they would be written (or left alone, when nothing changes)
        $rows = [];
        $totalNow = 0;
        $totalAfter = 0;
        $larger = false; // does a config get larger?
        foreach ($configs as $id => $config) {
            $plan = $plans[$id];
            $now = is_file($plan->path)
                ? (int)filesize($plan->path)
                : strlen(ConfigDirectory::encode($plan->current));
            $after = $plan->stripped === null || $plan->status === StripPlan::UNCHANGED
                ? $now
                : strlen(ConfigDirectory::encode($plan->stripped));
            $totalNow += $now;
            $totalAfter += $after;
            $larger = $larger || ($plan->stripped !== null && $after > $now);
            $rows[] = [
                $id,
                count($config->datamodel->meta ?? []),
                self::kb($now),
                $plan->stripped === null ? 'cannot be stripped' : self::kb($after),
                $plan->stripped === null ? '-' : self::win($now, $after),
            ];
        }
        $io->section('Configs (file sizes now, and as they would be written)');
        $io->table(['Config', 'Layers', 'Now', 'After', 'Win'], $rows);
        $genericAfter = $genericNow !== null && !$genericChanged
            ? $genericNow
            : strlen(ConfigDirectory::encode($result->generic));
        $io->writeln(
            $genericNow === null
                ? sprintf('%s.json: %s (new)', $genericName, self::kb($genericAfter))
                : sprintf(
                    '%s.json: %s now, %s after%s',
                    $genericName,
                    self::kb($genericNow),
                    self::kb($genericAfter),
                    $genericChanged ? '' : ' (unchanged)'
                )
        );
        $totalNow += $genericNow ?? 0;
        $totalAfter += $genericAfter;
        $io->writeln(sprintf(
            'All files, with %s.json: %s now, %s after (%s)',
            $genericName,
            self::kb($totalNow),
            self::kb($totalAfter),
            $totalAfter <= $totalNow
                ? self::win($totalNow, $totalAfter) . ' smaller'
                : sprintf('%.1f%% larger', 100 * ($totalAfter - $totalNow) / $totalNow)
        ));
        if ($larger) {
            $io->writeln(
                'A negative win: that config needs more overrides afterwards, because what most configs share '
                . '(the generic config) changed.'
            );
        }

        $io->section('Layers');
        $io->writeln(sprintf(
            '%d generic layers (each used by 2 or more configs); %d layers are used by one config only and stay in '.
            'its file. Region layer entries overriding a generic value, per field:',
            $result->genericLayerCount,
            $result->regionOnlyLayerCount
        ));
        $io->writeln('  ' . ($result->layerOverrides === [] ? 'none' : implode(', ', array_map(
            static fn($field, $count) => "$field: $count",
            array_keys($result->layerOverrides),
            $result->layerOverrides
        ))));
        $proposed = $registry->proposed();
        $io->writeln(sprintf(
            '%d layer names were not in layer_names of %s.json and got a proposed generic name.',
            count($proposed),
            $genericName
        ));

        if ($hadGeneric) {
            $io->section("Changes to $genericName.json");
            $map = $registry->toMap();
            $describe = static fn(array $names): string => implode(', ', array_map(
                static fn(string $name) => $name . (($map[$name] ?? []) === []
                    ? '' // no longer in the name map, for example renamed there
                    : ' (' . implode(', ', $map[$name]) . ')'),
                $io->isVerbose() ? $names : array_slice($names, 0, 8)
            )) . (!$io->isVerbose() && count($names) > 8 ? ', ... (-v lists all)' : '');
            $io->writeln($promoted === []
                ? 'No layer becomes generic.'
                : sprintf(
                    '%d layer(s) become generic, because 2 or more configs use them now: %s',
                    count($promoted),
                    $describe($promoted)
                ));
            $io->writeln($demoted === []
                ? 'No layer moves back to a single config.'
                : sprintf(
                    '%d layer(s) move back into the one config that uses them: %s',
                    count($demoted),
                    $describe($demoted)
                ));
        }

        $io->section('Sections: how much is actually shared between the configs');
        $groups = [];
        foreach ($result->support as $path => $info) {
            $parts = explode('.', $path);
            $group = in_array($parts[0], ['dependencies', 'restrictions', 'CEL'], true) ? $parts[0] : $parts[0] . '.' .
                ($parts[1] ?? '');
            $groups[$group] ??= [
                'kind' => $info['kind'],
                'values' => 0, 'generic' => 0,
                'of' => $info['of'],
                'best' => 0,
                'distinct' => 0
            ];
            $groups[$group]['values']++;
            $groups[$group]['generic'] += $info['generic'] ? 1 : 0;
            $groups[$group]['best'] += $info['best'];
            $groups[$group]['distinct'] += $info['distinct'];
        }
        ksort($groups);
        $rows = [];
        foreach ($groups as $group => $g) {
            $rows[] = [
                $group,
                $g['of'] . ' configs',
                $g['kind'] === 'list'
                    ? sprintf('%d of %d distinct items are generic (shared)', $g['best'], $g['distinct'])
                    : sprintf('%d of %d values shared by 2+ configs', $g['generic'], $g['values']),
            ];
        }
        $io->table(['Section', 'Participating', 'Generic result'], $rows);

        if ($result->dropped !== []) {
            $io->section('Removed / rewritten on purpose (design document)');
            $io->table(['What', 'Count'], array_map(
                static fn($what, $count) => [$what, $count],
                array_keys($result->dropped),
                $result->dropped
            ));
        }
        $warnings = array_merge($registry->warnings(), $result->warnings, $this->sameRegionWarnings($configs));
        if ($warnings !== []) {
            $io->section('Warnings');
            $io->listing($warnings);
        }
        if ($io->isVerbose() && $proposed !== []) {
            $io->section('Proposed generic names (review these)');
            $rows = [];
            foreach ($registry->toMap() as $generic => $names) {
                $rows[] = [$generic, implode(', ', $names)];
            }
            $io->table(['Generic name', 'Layer names'], $rows);
        }
    }
}
