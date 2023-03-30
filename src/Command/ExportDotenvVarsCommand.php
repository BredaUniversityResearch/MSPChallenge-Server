<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;

class ExportDotenvVarsCommand extends Command
{
    protected static $defaultName = 'app:export-dotenv-vars';

    public function __construct(private string $projectDir)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Export dotenv variables separated by space')
            ->addArgument(
                'names',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Dotenv variables to export by their name, separated by space'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $dotenv = new Dotenv();
        $dotenv->loadEnv($this->projectDir."/.env");
        $args = $input->getArgument('names');
        foreach ($args as $env) {
            if (!array_key_exists($env, $_ENV)) {
                continue;
            }
            $output->write($env."=".$_ENV[$env]." ");
        }
        return 0;
    }
}
