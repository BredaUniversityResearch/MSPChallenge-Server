<?php
namespace App\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

readonly class ContainerAwareMigrationFactory implements MigrationFactory
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private ContainerInterface $container
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $migration = new $migrationClassName(
            $this->connection,
            $this->logger
        );
        if ($migration instanceof ContainerAwareMigrationInterface) {
            $migration->setContainer($this->container);
        }
        return $migration;
    }
}

