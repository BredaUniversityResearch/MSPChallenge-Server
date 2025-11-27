<?php

namespace App\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:check-entity-manager',
    description: 'Checks which entity manager is responsible for a specific entity.'
)]
class CheckEntityManagerCommand extends Command
{
    private ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Checks which entity manager is responsible for a specific entity.')
            ->setHelp('This command verifies the entity manager responsible for a given entity class.')
            ->addArgument('entityClass', InputArgument::REQUIRED, 'The fully qualified class name of the entity.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entityClass = $input->getArgument('entityClass');
        $managers = $this->doctrine->getManagerNames();

        foreach ($managers as $managerName => $serviceId) {
            $manager = $this->doctrine->getManager($managerName);
            $metadataFactory = $manager->getMetadataFactory();

            if (!$metadataFactory->isTransient($entityClass)) {
                $output->writeln(sprintf(
                    'Entity "%s" is managed by the "%s" entity manager.',
                    $entityClass,
                    $managerName
                ));
                return Command::SUCCESS;
            }
        }

        $output->writeln(sprintf('No entity manager found for entity "%s".', $entityClass));
        return Command::FAILURE;
    }
}
