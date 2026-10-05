<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
use App\Domain\Config\InvalidSessionConfigException;
use App\Domain\Config\Merge\ConfigFileStripper;
use App\Domain\Config\Merge\RegionConfigMerger;
use App\Domain\Config\Merge\StripPlan;
use App\Domain\Config\SessionConfigValidator;
use App\Domain\Helper\Util;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'app:config:strip',
    description: 'Removes everything a generic config (the parent) already provides from config files (complete '
        . 'configs, or configs stripped against another parent). Reports only, unless --apply or --output-dir is '
        . 'given.'
)]
final class ConfigStripCommand extends ConfigCommand
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
            ->addArgument(
                'files',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Config files to strip (any location). Default: all configs in --dir'
            )
            ->addOption(
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Config root with the parent configs (absolute, or relative to the project dir)',
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
                'parent',
                null,
                InputOption::VALUE_REQUIRED,
                'The generic config to strip against: its name, a file <name>.json in --dir. By default the parent '
                . 'that a file names in metadata.parent (a complete config has none: then this option is needed)'
            )
            ->addOption(
                'apply',
                null,
                InputOption::VALUE_NONE,
                'Replace the files by their stripped versions. Each one is written to a temporary file, read back '
                . 'and verified (merged with its parent it must give the same config as before) first'
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Write nothing, exit with status 1 when a file could still be stripped (for CI)'
            )
            ->addOption(
                'output-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Write the stripped configs (the same paths below it) to this directory instead, leaving the files '
                . 'alone'
            )
            ->addOption(
                'skip-validation',
                null,
                InputOption::VALUE_NONE,
                'Do not validate complete configs against SessionConfigJSONSchema.json'
            )
            ->addFormatOption();
    }

    protected function perform(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        ConfigReport $report
    ): int {
        if ($input->getOption('apply') && $input->getOption('check')) {
            return $report->fail('Use either --apply or --check, not both.', 'invalid_usage', Command::INVALID);
        }
        $root = Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir);
        $outputDir = $input->getOption('output-dir');
        if (!is_dir($root)) {
            return $report->fail("Config directory not found: $root", 'dir_not_found');
        }
        $directory = new ConfigDirectory($root);
        $parents = ConfigParents::fromDirectory($directory);
        $merger = new RegionConfigMerger();
        $parentOption = $input->getOption('parent');
        if ($parentOption !== null && !ConfigDirectory::isValidParentName((string)$parentOption)) {
            return $report->fail(
                '--parent is the name of a file without the extension: letters, digits, _ and - only.',
                'invalid_usage',
                Command::INVALID
            );
        }

        // 1. the files
        $skipped = [];
        try {
            $files = $directory->resolveFiles(
                (array)$input->getArgument('files'),
                getcwd() ?: $this->projectDir,
                (string)$input->getOption('pattern'),
                $skipped
            );
        } catch (\Throwable $e) {
            return $report->failThrowable($e);
        }
        $writing = (bool)$input->getOption('apply') || $outputDir !== null;
        $stop = $this->skippedFiles($report, $directory, $skipped, $writing);
        if ($stop !== null) {
            return $stop;
        }
        if ($files === []) {
            $report->warning("No configs found in $root", 'no_configs');
            return Command::SUCCESS;
        }

        // 2. read, validate (complete configs only: stripped ones are not valid on their own) and plan
        $stripper = new ConfigFileStripper();
        $plans = [];
        $pools = []; // id => the generic config the file is stripped against
        $parentNames = []; // id => its name
        $registries = []; // parent name => the layer names of that generic config and its parents
        $errors = [];
        $errorDetails = []; // the same errors, told to programs
        foreach ($files as $id => $path) {
            try {
                $current = $directory->read($path);
                if (!RegionConfigMerger::isRegionFormat($current) && !$input->getOption('skip-validation')) {
                    // the schema describes the final config: validate the config in that shape
                    $this->validator->validate(RegionConfigMerger::toSimulationSettings($current));
                }
                $parentName = $parentOption ?? ConfigParents::parentOf($current);
                if ($parentName === null) {
                    throw new \RuntimeException(
                        'it has no metadata.parent: say which generic config to strip it against with --parent=NAME'
                    );
                }
                $parentName = (string)$parentName;
                $label = '"' . $id . '"';
                // its final config, with the parents it has now, and then stripped against the parent it gets
                $effective = $merger->merge($parents->poolOf($current, $label), $current);
                $pools[$id] = $parents->poolOfParent($parentName, $label);
                $registries[$parentName] ??= $parents->namesOfParent($parentName, $label);
                $parentNames[$id] = $parentName;
                $plans[$id] = $stripper->plan(
                    $id,
                    $path,
                    $current,
                    $pools[$id],
                    $registries[$parentName],
                    $effective,
                    $parentName
                );
            } catch (\Throwable $e) {
                $errors[] = sprintf(
                    '%s: %s',
                    $id,
                    $e instanceof InvalidSessionConfigException ? $e->summary() : strtok($e->getMessage(), "\n")
                );
                $errorDetails[] = ConfigReport::describe($e, $id);
            }
        }
        if ($errors !== []) {
            return $report->failWith(
                array_merge(['Cannot continue, these configs are invalid:'], $errors),
                $errorDetails
            );
        }

        // 3. report
        $this->report($io, $plans);
        $changed = array_filter($plans, static fn(StripPlan $plan) => $plan->status === StripPlan::CHANGED);
        $report->data = [
            'apply' => (bool)$input->getOption('apply'),
            'check' => (bool)$input->getOption('check'),
            'outputDir' => $outputDir === null ? null : (string)$outputDir,
            'results' => $this->results($plans, $parentNames),
            'written' => 0,
        ];

        // 4. write
        if ($input->getOption('check')) {
            if ($changed !== []) {
                return $report->finding(
                    sprintf('%d config(s) can be stripped further: run app:config:strip --apply.', count($changed)),
                    'check_failed',
                    ['configs' => count($changed)]
                );
            }
            $io->success('Nothing left to strip.');
            return Command::SUCCESS;
        }
        if (!$input->getOption('apply') && $outputDir === null) {
            $io->note(
                $changed === []
                    ? 'Nothing to change.'
                    : 'Dry run, nothing written. Run with --apply to replace the files (each one verified first), '
                        . 'or with --output-dir=DIR to write the result elsewhere.'
            );
            return Command::SUCCESS;
        }
        $outputRoot = $outputDir === null ? null : Path::makeAbsolute((string)$outputDir, $this->projectDir);
        $problems = [];
        $written = 0;
        try {
            foreach ($plans as $id => $plan) {
                $target = $outputRoot === null ? $plan->path : $outputRoot . '/' . $id . '.json';
                $skip = $plan->status === StripPlan::REFUSED
                    || ($outputRoot === null && $plan->status !== StripPlan::CHANGED);
                if ($skip) {
                    continue;
                }
                $found = $stripper->apply($plan, $directory, $pools[$id], $target, $outputRoot !== null);
                if ($found === []) {
                    $written++;
                }
                foreach ($found as $problem) {
                    $problems[] = "$id: $problem";
                }
            }
            if ($outputRoot !== null) {
                // the parents the files need go along, so the directory can be used on its own
                $outputDirectory = new ConfigDirectory($outputRoot);
                foreach (array_unique($parentNames) as $parentName) {
                    foreach ($parents->chain($parentName) as $name => $parentConfig) {
                        $outputDirectory->write($outputDirectory->parentPath($name), $parentConfig);
                    }
                }
            }
        } catch (\Throwable $e) {
            return $report->failThrowable($e);
        }
        $report->data['written'] = $written;
        if ($problems !== []) {
            return $report->failWith(
                array_merge(['Not everything could be written and verified, those files are untouched:'], $problems),
                array_map(static fn(string $problem) => ['code' => 'write_failed', 'message' => $problem], $problems)
            );
        }
        $io->success(sprintf(
            '%d config(s) %s%s.',
            $written,
            $outputRoot === null ? 'stripped in place' : 'written to ' . $outputRoot,
            ' (each verified: merged with its parent it gives the same config)'
        ));
        return Command::SUCCESS;
    }

    /**
     * What is known of every config, for programs.
     *
     * @param array<string, StripPlan> $plans
     * @param array<string, string> $parentNames config id => the name of the parent it is stripped against
     * @return list<array<string, mixed>>
     */
    private function results(array $plans, array $parentNames): array
    {
        $results = [];
        foreach ($plans as $id => $plan) {
            $now = is_file($plan->path) ? (int)filesize($plan->path) : strlen(ConfigDirectory::encode($plan->current));
            $after = $plan->stripped === null || $plan->status === StripPlan::UNCHANGED
                ? $now
                : strlen(ConfigDirectory::encode($plan->stripped));
            $results[] = [
                'id' => $id,
                'path' => $plan->path,
                'status' => match ($plan->status) {
                    StripPlan::CHANGED => 'will_be_stripped',
                    StripPlan::UNCHANGED => 'already_stripped',
                    StripPlan::REFUSED => 'kept_as_is',
                },
                'parent' => $parentNames[$id] ?? null,
                'nowBytes' => $now,
                'afterBytes' => $after,
                'reasons' => $plan->reasons,
                'warnings' => $plan->warnings,
            ];
        }
        return $results;
    }

    /**
     * @param array<string, StripPlan> $plans
     */
    private function report(SymfonyStyle $io, array $plans): void
    {
        $rows = [];
        $counts = [StripPlan::CHANGED => 0, StripPlan::UNCHANGED => 0, StripPlan::REFUSED => 0];
        foreach ($plans as $id => $plan) {
            $counts[$plan->status]++;
            $rows[] = [
                $id,
                match ($plan->status) {
                    StripPlan::CHANGED => 'will be stripped',
                    StripPlan::UNCHANGED => 'already stripped',
                    StripPlan::REFUSED => 'kept as is',
                },
                Util::getHumanReadableSize(strlen(json_encode($plan->current, JSON_UNESCAPED_SLASHES))),
                $plan->stripped === null
                    ? '-'
                    : Util::getHumanReadableSize(strlen(json_encode($plan->stripped, JSON_UNESCAPED_SLASHES))),
                $plan->status === StripPlan::REFUSED ? (string)($plan->reasons[0] ?? '') : '',
            ];
        }
        $io->table(['Config', 'Result', 'Now', 'Stripped', 'Why'], $rows);
        $io->writeln(sprintf(
            '%d to strip, %d already stripped, %d kept as is.',
            $counts[StripPlan::CHANGED],
            $counts[StripPlan::UNCHANGED],
            $counts[StripPlan::REFUSED]
        ));
        foreach ($plans as $plan) {
            if ($plan->status === StripPlan::REFUSED && $plan->reasons !== []) {
                $io->writeln(' - ' . $plan->id . ': ' . implode(' ', array_slice($plan->reasons, 0, 4)));
            }
            if ($io->isVerbose()) {
                foreach ($plan->warnings as $warning) {
                    $io->writeln(' - ' . $plan->id . ': ' . $warning);
                }
            }
        }
    }
}
