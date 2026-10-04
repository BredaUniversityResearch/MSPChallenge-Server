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
use App\Domain\Helper\Util;
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
                'Config root (absolute, or relative to the project dir)',
                'ServerManager/configfiles'
            )
            ->addOption(
                'pattern',
                null,
                InputOption::VALUE_REQUIRED,
                'File name pattern of the complete configs, one folder below --dir',
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
        try {
            $oldGeneric = $directory->hasGeneric($genericName) ? $directory->loadGeneric($genericName) : null;
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
        $effectives = []; // the expanded stripped ones
        $paths = [];
        $errors = [];
        foreach ($directory->configFiles((string)$input->getOption('pattern')) as $id => $path) {
            try {
                $config = $directory->read($path);
                if (RegionConfigMerger::isRegionFormat($config)) {
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
        if ($effectives !== []) {
            $io->writeln(sprintf(
                '%d of them are stripped already: they are expanded with their parents, so layers that more (or '
                . 'fewer) configs use now can move into (or out of) %s.json.',
                count($effectives),
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
            $demoted
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
        $genericPath = $outputRoot . '/' . $genericName . '.json';
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
        $io->success(sprintf(
            'Wrote %s and %d stripped config(s)%s.',
            ConfigDirectory::displayPath($genericPath, $this->projectDir),
            count($staged),
            $outputDir === null ? ' (replacing the existing ones, each verified)' : ' to ' . $outputRoot
        ));
        return Command::SUCCESS;
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
        array $demoted
    ): void {
        $rows = [];
        foreach ($configs as $id => $config) {
            $stripped = $plans[$id]->stripped;
            $rows[] = [
                $id,
                count($config->datamodel->meta ?? []),
                Util::getHumanReadableSize(strlen(json_encode($plans[$id]->current, JSON_UNESCAPED_SLASHES))),
                $stripped === null
                    ? 'cannot be stripped'
                    : Util::getHumanReadableSize(strlen(json_encode($stripped, JSON_UNESCAPED_SLASHES))),
            ];
        }
        $io->section('Configs (sizes of compact JSON)');
        $io->table(['Config', 'Layers', 'Original', 'Stripped'], $rows);
        $genericSize = Util::getHumanReadableSize(strlen(json_encode($result->generic, JSON_UNESCAPED_SLASHES)));
        $io->writeln("$genericName.json: $genericSize");

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
        $warnings = array_merge($registry->warnings(), $result->warnings);
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
