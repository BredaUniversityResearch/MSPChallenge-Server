<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
use App\Domain\Config\ConfigParents;
use App\Domain\Config\Merge\RegionConfigMerger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'app:config:merge',
    description: 'Prints the final config of a config file: merged with its parents, in the shape the server '
        . 'uses (CEL, SEL and MEL in simulation_settings; old-style CEL, SEL and MEL keys are laid on top of them).'
)]
final class ConfigMergeCommand extends ConfigCommand
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
            ->addArgument('file', InputArgument::REQUIRED, 'The config file (stripped, or complete)')
            ->addOption(
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Config root, where the parent configs are (anywhere below it). Absolute, or relative to the '
                . 'project dir',
                $this->defaultDir
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Write the result to this file instead of printing it'
            )
            ->addExcludeOption()
            ->addFormatOption();
    }

    /** The config is printed on stdout: the messages for people must not get in between. */
    protected function messagesToStderr(): bool
    {
        return true;
    }

    protected function resultKeys(): array
    {
        return ['config'];
    }

    protected function rawOutput(InputInterface $input, ConfigReport $report): ?string
    {
        $config = $report->data['config'] ?? null;
        return $input->getOption('output') === null && $config instanceof \stdClass
            ? ConfigDirectory::encode($config)
            : null;
    }

    protected function perform(InputInterface $input, ConfigReport $report): int
    {
        $directory = new ConfigDirectory(
            Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir),
            $this->excludesOf($input)
        );
        /** @var \ArrayObject<string, array{path: string, fingerprint: string}> $fingerprints */
        $fingerprints = new \ArrayObject();
        try {
            $path = $directory->resolveFiles([(string)$input->getArgument('file')], getcwd() ?: $this->projectDir);
            $id = (string)array_key_first($path);
            $config = $directory->read(reset($path));
            $pool = ConfigParents::fromDirectory($directory, [], $fingerprints)->poolOf($config, '"' . $id . '"');
            $warnings = [];
            $merged = new RegionConfigMerger()->merge($pool, $config, $warnings);
        } catch (\Throwable $e) {
            return $report->failThrowable($e, (string)$input->getArgument('file'));
        }
        foreach ($warnings as $warning) {
            $report->warning($warning, 'merge_warning');
        }
        $report->data['file'] = $id;
        // the parents the config was merged with, nearest parent first, and where they were found
        $report->data['parents'] = [];
        foreach ($fingerprints->getArrayCopy() as $name => $found) {
            $report->data['parents'][] = ['name' => $name] + $found;
        }
        if ($input->getOption('output') === null) {
            $report->data['config'] = $merged;
            return Command::SUCCESS;
        }
        $target = Path::makeAbsolute((string)$input->getOption('output'), getcwd() ?: $this->projectDir);
        $directory->write($target, $merged);
        $report->data['output'] = $target;
        return Command::SUCCESS;
    }
}
