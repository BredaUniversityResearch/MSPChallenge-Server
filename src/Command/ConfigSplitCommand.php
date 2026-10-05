<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'app:config:split',
    description: 'Derives a generic config (--generic) from the configs below --dir (complete ones, '
        . 'or ones that are stripped already: those are expanded with their parents first) and strips every config '
        . 'down to what is specific for its region, with the generic config as its parent. Reports only, unless '
        . '--apply or --output-dir is given.'
)]
final class ConfigSplitCommand extends ConfigCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly SessionConfigValidator $validator,
        private readonly string $defaultDir = 'ServerManager/configfiles'
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
                $this->defaultDir
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
            )
            ->addFormatOption();
    }

    protected function perform(InputInterface $input, ConfigReport $report): int
    {
        $root = Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir);
        $outputDir = $input->getOption('output-dir');
        $outputRoot = $outputDir === null ? $root : Path::makeAbsolute((string)$outputDir, $this->projectDir);
        $check = (bool)$input->getOption('check');
        $writing = $input->getOption('apply') || $outputDir !== null;
        if ($check && $writing) {
            return $report->fail('Use --check on its own, it never writes.', 'invalid_usage', Command::INVALID);
        }
        if (!is_dir($root)) {
            return $report->fail("Config directory not found: $root", 'dir_not_found');
        }
        $directory = new ConfigDirectory($root);
        $genericName = (string)$input->getOption('generic');
        if (!ConfigDirectory::isValidParentName($genericName)) {
            return $report->fail(
                '--generic is the name of a file without the extension: letters, digits, _ and - only.',
                'invalid_usage',
                Command::INVALID
            );
        }

        // 1. read the configs. A config that is stripped already is expanded with its parents
        $oldGeneric = null;
        $genericFile = null; // where the generic config is now, if there is one
        try {
            $genericFile = $directory->locateParent($genericName);
            $oldGeneric = $genericFile === null ? null : $directory->read($genericFile);
            if ($oldGeneric !== null && ConfigParents::parentOf($oldGeneric) !== null) {
                return $report->fail(
                    "$genericName.json has a parent itself, and the command makes a generic config without one. "
                    . 'Choose another name with --generic.',
                    'generic_has_parent',
                    details: ['generic' => $genericName]
                );
            }
        } catch (\Throwable $e) {
            $report->warning(
                "Ignoring $genericName.json, it cannot be used: " . $e->getMessage(),
                'generic_unusable',
                ['generic' => $genericName]
            );
        }
        $parents = ConfigParents::fromDirectory($directory);
        $merger = new RegionConfigMerger();
        $configs = []; // what is split: the complete configs, and the expanded stripped ones
        $currents = []; // the files as they are now
        $effectives = []; // the expanded stripped ones, and the complete ones that are in the new shape
        $strippedCount = 0; // how many of those really are stripped
        $paths = [];
        $errors = []; // what is wrong with the configs
        $skipped = [];
        $found = $directory->configFiles((string)$input->getOption('pattern'), $skipped);
        if ($genericFile !== null) {
            unset($skipped[$genericFile]); // the generic config is told about by itself when it can not be used
        }
        $stop = $this->skippedFiles($report, $directory, $skipped, (bool)$writing);
        if ($stop !== null) {
            return $stop;
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
                $errors[] = ConfigReport::describe($e, $id);
            }
        }
        if ($errors !== []) {
            return $report->failWith($errors);
        }
        if (count($configs) < 2) {
            $tooFew = sprintf(
                'Found %d config(s) in %s; at least 2 are needed to find anything generic.',
                count($configs),
                $root
            );
            if ($configs === []) {
                return $report->finding($tooFew, 'no_configs');
            }
            $report->warning($tooFew, 'too_few_configs');
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
            return $report->failThrowable($e);
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

        // 3. what was found
        $this->describe(
            $report,
            $root,
            $strippedCount,
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
        $report->data['apply'] = (bool)$input->getOption('apply');
        $report->data['check'] = $check;
        $report->data['outputDir'] = $outputDir === null ? null : (string)$outputDir;
        $refused = array_filter($plans, static fn(StripPlan $plan) => $plan->status === StripPlan::REFUSED);
        foreach ($refused as $plan) {
            $report->warning(
                $plan->id . ': ' . implode(' ', array_slice($plan->reasons, 0, 3)),
                'config_not_strippable',
                ['config' => $plan->id]
            );
        }

        // 4. check, or write
        if ($check) {
            if ($genericChanged || $changed !== []) {
                return $report->finding(
                    sprintf(
                        'Running with --apply would change %s and %d config(s).',
                        $genericChanged ? "$genericName.json" : "nothing in $genericName.json",
                        count($changed)
                    ),
                    'check_failed',
                    ['genericChanged' => $genericChanged, 'configs' => count($changed)]
                );
            }
            return Command::SUCCESS;
        }
        if (!$writing) {
            return Command::SUCCESS;
        }
        $unsafe = array_filter(
            $refused,
            static fn(StripPlan $plan) => $outputDir === null && isset($effectives[$plan->id])
        );
        if ($unsafe !== []) {
            return $report->fail(
                'Nothing is written: these stripped configs cannot be written for the new generic config: '
                . implode(', ', array_map(static fn(StripPlan $plan) => $plan->id, $unsafe)),
                'stripped_configs_unsafe',
                details: ['configs' => array_values(array_map(static fn(StripPlan $plan) => $plan->id, $unsafe))]
            );
        }
        // an existing generic config is written where it is (also when it is deeper in the tree); a new one goes in the
        // root of where is written. Another folder to write to gets a flat copy.
        $genericPath = ($outputDir === null ? $genericFile : null) ?? $outputRoot . '/' . $genericName . '.json';
        if (is_file($genericPath) && !$input->getOption('force')) {
            return $report->fail(
                $directory->relativePath($genericPath) . ' exists (it may have been edited by hand), '
                . 'use --force to overwrite it.',
                'generic_exists',
                details: ['path' => $directory->relativePath($genericPath)]
            );
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
                return $report->failWith(array_map(
                    static fn(string $problem) => ['code' => 'write_failed', 'message' => $problem],
                    $problems
                ));
            }
            if ($outputDir !== null || $genericChanged) {
                $outputDirectory->write($genericPath, $result->generic);
            }
            foreach ($staged as [$temporary, $target]) {
                $outputDirectory->commit($temporary, $target);
            }
        } catch (\Throwable $e) {
            $outputDirectory->discard(...array_column($staged, 0));
            return $report->failThrowable($e);
        }
        $genericWritten = $outputDir !== null || $genericChanged;
        $report->data['written'] = [
            'generic' => $genericWritten,
            'genericPath' => $directory->relativePath($genericPath),
            'configs' => count($staged),
            'nothingToWrite' => !$genericWritten && $staged === [],
        ];
        return Command::SUCCESS;
    }

    /**
     * Every config counts as a region when it is decided what is shared. Copies of one config, and versions of it,
     * are no separate regions: they make their data look more shared than it is, and shift what is generic towards
     * them. Say so, so that they can be left out (--pattern).
     *
     * @param array<string, \stdClass> $configs
     * @return list<array{code: string, message: string, details: array<string, mixed>}>
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
                $warnings[] = [
                    'code' => 'same_content',
                    'message' => sprintf(
                        'These configs have the same content: %s. They count as separate regions, so what they have '
                        . 'is shared by definition. Leave out the copies (--pattern) to see what is really shared.',
                        implode(', ', $ids)
                    ),
                    'details' => ['configs' => $ids],
                ];
            }
        }
        $versions = [];
        foreach (array_keys($configs) as $id) {
            $versions[dirname((string)$id)][] = basename((string)$id);
        }
        foreach ($versions as $folder => $names) {
            if (count($names) > 1) {
                $warnings[] = [
                    'code' => 'several_versions',
                    'message' => sprintf(
                        '%s has %d versions (%s). Each version counts as a region of its own.',
                        $folder,
                        count($names),
                        implode(', ', $names)
                    ),
                    'details' => ['folder' => $folder, 'versions' => $names],
                ];
            }
        }
        return $warnings;
    }

    /**
     * What was found, for the report: the data of the result.
     *
     * @param array<string, \stdClass> $configs
     * @param array<string, StripPlan> $plans
     * @param string[] $promoted
     * @param string[] $demoted
     */
    private function describe(
        ConfigReport $report,
        string $root,
        int $strippedCount,
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
            $rows[] = [
                'id' => $id,
                'path' => $plan->path,
                'layers' => count($config->datamodel->meta ?? []),
                'status' => match ($plan->status) {
                    StripPlan::CHANGED => 'will_be_stripped',
                    StripPlan::UNCHANGED => 'already_stripped',
                    StripPlan::REFUSED => 'kept_as_is',
                },
                'nowBytes' => $now,
                'afterBytes' => $after,
                'reasons' => $plan->reasons,
            ];
        }
        $genericAfter = $genericNow !== null && !$genericChanged
            ? $genericNow
            : strlen(ConfigDirectory::encode($result->generic));
        $totalNow += $genericNow ?? 0;
        $totalAfter += $genericAfter;

        // how much of every section is shared
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
        $sections = [];
        foreach ($groups as $group => $g) {
            $sections[] = [
                'name' => $group,
                'participating' => $g['of'],
                'kind' => $g['kind'],
                'values' => $g['values'],
                'genericValues' => $g['generic'],
                'distinctItems' => $g['distinct'],
                'genericItems' => $g['best'],
            ];
        }

        foreach (array_merge($registry->warnings(), $result->warnings) as $warning) {
            $report->warning($warning, 'split_warning');
        }
        foreach ($this->sameRegionWarnings($configs) as $warning) {
            $report->warning($warning['message'], $warning['code'], $warning['details']);
        }
        $report->data += [
            'root' => $root,
            'strippedCount' => $strippedCount,
            'configs' => $rows,
            'generic' => [
                'name' => $genericName,
                'existed' => $hadGeneric,
                'changed' => $genericChanged,
                'nowBytes' => $genericNow,
                'afterBytes' => $genericAfter,
            ],
            'total' => ['nowBytes' => $totalNow, 'afterBytes' => $totalAfter],
            'layers' => [
                'generic' => $result->genericLayerCount,
                'regionOnly' => $result->regionOnlyLayerCount,
                'overrides' => (object)$result->layerOverrides,
                'proposedNames' => count($registry->proposed()),
                'promoted' => $promoted,
                'demoted' => $demoted,
                'nameMap' => (object)$registry->toMap(),
            ],
            'sections' => $sections,
            'removed' => (object)$result->dropped,
        ];
    }
}
