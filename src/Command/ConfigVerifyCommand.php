<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\Merge\ConfigComparator;
use App\Domain\Config\Merge\ConfigNormalizer;
use App\Domain\Config\Merge\RegionConfigMerger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:config:verify',
    description: 'Checks that config files, merged with generic.json, still give the original configs: the '
        . 'versions from a git revision, or from a directory.'
)]
final class ConfigVerifyCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
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
                'Config root with generic.json (absolute, or relative to the project dir)',
                'ServerManager/configfiles'
            )
            ->addOption(
                'pattern',
                null,
                InputOption::VALUE_REQUIRED,
                'File name pattern of the configs, one folder below --dir',
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
                'Take the originals from <dir>/<folder>/<name>.json instead of git'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = new ConfigDirectory(Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir));
        $repo = Path::makeAbsolute((string)$input->getOption('repo'), $this->projectDir);
        $originalDir = $input->getOption('original-dir');
        try {
            $generic = $directory->loadGeneric();
            $files = $directory->resolveFiles(
                (array)$input->getArgument('files'),
                getcwd() ?: $this->projectDir,
                (string)$input->getOption('pattern')
            );
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        if ($files === []) {
            $io->warning('No configs found.');
            return Command::SUCCESS;
        }

        $merger = new RegionConfigMerger();
        $normalizer = new ConfigNormalizer();
        $comparator = new ConfigComparator();
        $source = $originalDir === null
            ? 'git revision ' . $input->getOption('against')
            : Path::makeAbsolute((string)$originalDir, $this->projectDir);
        $failed = 0;
        $rows = [];
        $details = [];
        foreach ($files as $id => $path) {
            try {
                $original = $originalDir === null
                    ? $this->originalFromGit($repo, (string)$input->getOption('against'), $path)
                    : $directory->read($source . '/' . $id . '.json');
                $differences = $comparator->differences(
                    $normalizer->normalize($original),
                    $normalizer->normalize($merger->merge($generic, $directory->read($path)))
                );
            } catch (\Throwable $e) {
                $differences = ['Could not compare: ' . strtok($e->getMessage(), "\n")];
            }
            $rows[] = [$id, $differences === [] ? 'same as the original' : 'DIFFERENT'];
            if ($differences !== []) {
                $failed++;
                $details[$id] = $differences;
            }
        }
        $io->title('Merged with generic.json, compared with ' . $source);
        $io->table(['Config', 'Result'], $rows);
        foreach ($details as $id => $differences) {
            $io->section($id);
            $io->listing($differences);
        }
        if ($failed > 0) {
            $io->error(sprintf('%d of %d config(s) do not match their original.', $failed, count($files)));
            return Command::FAILURE;
        }
        $io->success(sprintf('All %d config(s) give the original config.', count($files)));
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
