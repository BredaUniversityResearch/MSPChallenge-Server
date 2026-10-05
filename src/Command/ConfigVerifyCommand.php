<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
use App\Domain\Config\Merge\ConfigComparator;
use App\Domain\Config\Merge\ConfigNormalizer;
use App\Domain\Config\Merge\RegionConfigMerger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:config:verify',
    description: 'Checks that config files, merged with their parents, still give the original configs: the '
        . 'versions from a git revision, or from a directory.'
)]
final class ConfigVerifyCommand extends ConfigCommand
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
            ->addArgument(
                'files',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Config files to verify (any location inside the repository). Default: all configs in --dir'
            )
            ->addOption(
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Config root with the parent configs, anywhere below it (absolute, or relative to the project dir)',
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
                'against',
                null,
                InputOption::VALUE_REQUIRED,
                'Git revision with the original configs (commit, tag, branch)',
                'HEAD'
            )
            ->addOption(
                'repo',
                null,
                InputOption::VALUE_REQUIRED,
                'The git repository (absolute, or relative to the project dir)',
                '.'
            )
            ->addOption(
                'original-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Take the originals from a folder, with the same paths below it as the configs have, instead of git'
            )
            ->addFormatOption();
    }

    protected function perform(InputInterface $input, ConfigReport $report): int
    {
        $directory = new ConfigDirectory(Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir));
        $repo = Path::makeAbsolute((string)$input->getOption('repo'), $this->projectDir);
        $originalDir = $input->getOption('original-dir');
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
        $this->skippedFiles($report, $directory, $skipped, false); // verify only says so
        if ($files === []) {
            $report->warning('No configs found.', 'no_configs');
            return Command::SUCCESS;
        }

        $merger = new RegionConfigMerger();
        $parents = ConfigParents::fromDirectory($directory);
        $normalizer = new ConfigNormalizer();
        $comparator = new ConfigComparator();
        $source = $originalDir === null
            ? 'git revision ' . $input->getOption('against')
            : Path::makeAbsolute((string)$originalDir, $this->projectDir);
        $failed = 0;
        $results = [];
        foreach ($files as $id => $path) {
            try {
                $original = $originalDir === null
                    ? $this->originalFromGit($repo, (string)$input->getOption('against'), $path)
                    : $directory->read($source . '/' . $id . '.json');
                $config = $directory->read($path);
                $differences = $comparator->differences(
                    $normalizer->normalize($original),
                    $normalizer->normalize($merger->merge($parents->poolOf($config, '"' . $id . '"'), $config))
                );
            } catch (\Throwable $e) {
                $differences = ['Could not compare: ' . strtok($e->getMessage(), "\n")];
            }
            $results[] = [
                'id' => $id,
                'path' => $directory->relativePath($path),
                'same' => $differences === [],
                'differences' => $differences,
            ];
            $failed += $differences === [] ? 0 : 1;
        }
        $report->data = ['source' => $source, 'results' => $results, 'failed' => $failed];
        if ($failed > 0) {
            return $report->fail(
                sprintf('%d of %d config(s) do not match their original.', $failed, count($files)),
                'verify_failed',
                details: ['failed' => $failed, 'total' => count($files)]
            );
        }
        return Command::SUCCESS;
    }

    /**
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function originalFromGit(string $repo, string $revision, string $path): \stdClass
    {
        if (!Path::isBasePath($repo, $path)) {
            throw new \RuntimeException("$path is not inside the repository $repo");
        }
        $relative = Path::makeRelative($path, $repo);
        $process = new Process(['git', '-C', $repo, 'show', $revision . ':' . $relative]);
        $process->setTimeout(60);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                "git cannot show $relative at $revision: " . trim($process->getErrorOutput())
            );
        }
        $original = json_decode(ltrim($process->getOutput(), "\xEF\xBB\xBF"), false, 512, JSON_THROW_ON_ERROR);
        if (!$original instanceof \stdClass) {
            throw new \RuntimeException("$relative at $revision is not a JSON object");
        }
        return $original;
    }
}
