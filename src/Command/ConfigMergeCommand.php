<?php

namespace App\Command;

use App\Domain\Config\ConfigDirectory;
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

#[AsCommand(
    name: 'app:config:merge',
    description: 'Prints the final config of a config file: merged with generic.json, in the shape the server '
        . 'uses (CEL, SEL and MEL in simulation_settings; old-style CEL, SEL and MEL keys are laid on top of them).'
)]
final class ConfigMergeCommand extends Command
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
            ->addArgument('file', InputArgument::REQUIRED, 'The config file (stripped, or complete)')
            ->addOption(
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Config root with generic.json (absolute, or relative to the project dir)',
                'ServerManager/configfiles'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Write the result to this file instead of printing it'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = (new SymfonyStyle($input, $output))->getErrorStyle();
        $directory = new ConfigDirectory(Path::makeAbsolute((string)$input->getOption('dir'), $this->projectDir));
        try {
            $generic = $directory->loadGeneric();
            $path = $directory->resolveFiles([(string)$input->getArgument('file')], getcwd() ?: $this->projectDir);
            $warnings = [];
            $merged = new RegionConfigMerger()->merge($generic, $directory->read(reset($path)), $warnings);
            $json = ConfigDirectory::encode($merged);
        } catch (\Throwable $e) {
            $errors->error($e->getMessage());
            return Command::FAILURE;
        }
        foreach ($warnings as $warning) {
            $errors->warning($warning);
        }
        if ($input->getOption('output') === null) {
            $output->write($json, false, OutputInterface::OUTPUT_RAW);
            return Command::SUCCESS;
        }
        $target = Path::makeAbsolute((string)$input->getOption('output'), getcwd() ?: $this->projectDir);
        $directory->write($target, $merged);
        $errors->success('Wrote ' . $target);
        return Command::SUCCESS;
    }
}
