<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
use App\Domain\Config\Merge\RegionConfigMerger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'app:config:merge-all',
    description: 'Merges every config below --dir with its parents and writes the final configs to --output-dir, with '
        . 'the same names and folders (the parents are not written: a final config needs none). A complete config is '
        . 'written in the current shape too. Reports only, unless --output-dir is given.'
)]
final class ConfigMergeAllCommand extends ConfigCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
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
                'Config root: the configs and the parents are anywhere below it. Absolute, or relative to the project '
                . 'dir',
                $this->defaultDir
            )
            ->addOption(
                'pattern',
                null,
                InputOption::VALUE_REQUIRED,
                'File name pattern of the configs to merge, anywhere below --dir',
                '*.json'
            )
            ->addOption(
                'output-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Write the final configs here, as <name>.json in the same folders as below --dir. Not in or below '
                . '--dir. Files that are there are replaced. Without it nothing is written'
            )
            ->addFormatOption();
    }

    protected function perform(InputInterface $input, ConfigReport $report): int
    {
        $root = Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir);
        $outputOption = $input->getOption('output-dir');
        $outputRoot = $outputOption === null ? null : Path::makeAbsolute((string)$outputOption, $this->projectDir);
        if (!is_dir($root)) {
            return $report->fail("Config directory not found: $root", 'dir_not_found');
        }
        if ($outputRoot !== null && self::isInside($outputRoot, $root)) {
            // it would be read as configs the next time, and a name that is the same would replace a source
            return $report->fail(
                "--output-dir ($outputRoot) is the config directory, or is in it ($root): use a folder outside it.",
                'invalid_usage',
                Command::INVALID
            );
        }
        $directory = new ConfigDirectory($root);
        $skipped = [];
        $files = $directory->configFiles((string)$input->getOption('pattern'), $skipped);
        $stop = $this->skippedFiles($report, $directory, $skipped, $outputRoot !== null);
        if ($stop !== null) {
            return $stop;
        }
        if ($files === []) {
            $report->warning("No configs found in $root", 'no_configs');
            return Command::SUCCESS;
        }

        // 1. merge them all, in memory: when one of them can not be merged nothing is written
        $parents = ConfigParents::fromDirectory($directory);
        $merger = new RegionConfigMerger();
        $results = [];
        $contents = []; // id => the final config as JSON, only when it is going to be written
        $errors = [];
        foreach ($files as $id => $path) {
            try {
                $config = $directory->read($path);
                $parentName = ConfigParents::parentOf($config);
                $pool = $parents->poolOf($config, '"' . $id . '"');
                $warnings = [];
                $json = ConfigDirectory::encode($merger->merge($pool, $config, $warnings));
            } catch (\Throwable $e) {
                $errors[] = ConfigReport::describe($e, $id);
                continue;
            }
            foreach ($warnings as $warning) {
                $report->warning($warning, 'merge_warning', ['file' => $id]);
            }
            $results[] = [
                'id' => $id,
                'path' => $directory->relativePath($path),
                'kind' => $parentName === null ? 'complete' : 'stripped',
                'parents' => $parentName === null ? [] : array_map('strval', array_keys($parents->chain($parentName))),
                'output' => $id . '.json',
                'bytes' => strlen($json),
            ];
            if ($outputRoot !== null) {
                $contents[$id] = $json;
            }
        }
        if ($errors !== []) {
            return $report->failWith($errors);
        }
        $report->data = [
            'root' => $root,
            'outputDir' => $outputRoot === null ? null : (string)$outputOption,
            'results' => $results,
            'written' => 0,
        ];
        if ($outputRoot === null) {
            return Command::SUCCESS;
        }

        // 2. write them (each file is replaced in one step)
        $filesystem = new Filesystem();
        $problems = [];
        $written = 0;
        foreach ($contents as $id => $json) {
            try {
                $filesystem->dumpFile($outputRoot . '/' . $id . '.json', $json);
                $written++;
            } catch (\Throwable $e) {
                $problems[] = ['code' => 'write_failed', 'message' => $e->getMessage(), 'file' => $id];
            }
        }
        $report->data['written'] = $written;
        return $problems === [] ? Command::SUCCESS : $report->failWith($problems);
    }

    /**
     * Is a path the same as a folder, or in it? (Windows does not tell A and a apart.)
     */
    private static function isInside(string $path, string $folder): bool
    {
        $path = rtrim(Path::canonicalize($path), '/') . '/';
        $folder = rtrim(Path::canonicalize($folder), '/') . '/';
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $folder = strtolower($folder);
        }
        return str_starts_with($path, $folder);
    }
}
