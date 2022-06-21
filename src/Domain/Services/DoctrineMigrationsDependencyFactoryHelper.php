<?php

namespace App\Domain\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ConnectionRegistryConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Persistence\AbstractManagerRegistry;

class DoctrineMigrationsDependencyFactoryHelper
{
    private string $projectDir;
    private ConnectionManager $connectionManager;

    public function __construct(string $projectDir, ConnectionManager $connectionManager)
    {
        $this->projectDir = $projectDir;
        $this->connectionManager = $connectionManager;
    }

    /**
     * @throws Exception
     */
    public function getDependencyFactory(?string $dbNameFilter = null): DependencyFactory
    {
        $dbNames = $this->connectionManager->getDbNames();
        if (null !== $dbNameFilter) {
            if (!in_array($dbNameFilter, $dbNames)) {
                throw new Exception('Encountered non-existing database: '. $dbNameFilter);
            }
            $dbNames = [$dbNameFilter];
        }
        $defaultDbName = $dbNameFilter ??
            // try to use a msp_session_X database if any
            (current(array_diff($dbNames, [$this->connectionManager->getServerManagerDbName()])) ?:
            $this->connectionManager->getServerManagerDbName());
        $configuration = new Configuration();
        $connectionRegistry = new class(
            'msp_connection_registry',
            array_combine($dbNames, $dbNames),
            [], // entity managers
            $defaultDbName, // default connection
            'default', // default entity manager
            'Doctrine\Persistence\Proxy' // proxy class
        ) extends AbstractManagerRegistry {
            // implement abstract methods here
            protected function getService(string $name): Connection
            {
                return ConnectionManager::getInstance()->getCachedDbConnection($name);
            }

            protected function resetService(string $name): Connection
            {
                return ConnectionManager::getInstance()->getCachedDbConnection($name);
            }
        };

        $migrationsDir = $this->projectDir . '/migrations';
        $configuration->addMigrationsDirectory('DoctrineMigrations', $migrationsDir);
        $configuration->setAllOrNothing(true);
        $configuration->setCheckDatabasePlatform(false);
        $configuration->setCustomTemplate($migrationsDir . '/template/template.tpl');

        $storageConfiguration = new TableMetadataStorageConfiguration();
        $storageConfiguration->setTableName('doctrine_migration_versions');

        $configuration->setMetadataStorageConfiguration($storageConfiguration);

        $configurationLoader = new ExistingConfiguration($configuration);
        $connectionLoader = ConnectionRegistryConnection::withSimpleDefault($connectionRegistry);
        return DependencyFactory::fromConnection(
            $configurationLoader,
            $connectionLoader
        );
    }
}
