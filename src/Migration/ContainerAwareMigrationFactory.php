<?php
namespace App\Migration;

use App\Domain\Services\ConnectionManager;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

readonly class ContainerAwareMigrationFactory implements MigrationFactory
{
    public function __construct(
        private MigrationFactory $migrationFactory,
        private ContainerInterface $container,
        private ConnectionManager $connectionManager
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        /** @var MSPMigration $migration */
        $migration = $this->migrationFactory->createVersion($migrationClassName);
        $migration->setConnectionManager($this->connectionManager);
        if ($migration  instanceof ContainerAwareMigrationInterface) {
            $migration->setContainer($this->container);
        }
        return $migration;
    }
}
