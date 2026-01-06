<?php
namespace App\Migration;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

readonly class ContainerAwareMigrationFactory implements MigrationFactory
{
    public function __construct(
        private MigrationFactory $migrationFactory,
        private ContainerInterface $container
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $migration = $this->migrationFactory->createVersion($migrationClassName);
        if ($migration  instanceof ContainerAwareMigrationInterface) {
            $migration->setContainer($this->container);
        }
        return $migration;
    }
}

