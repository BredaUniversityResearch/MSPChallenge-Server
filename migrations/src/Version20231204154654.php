<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231204154654 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add index to geometry_subtractive column in geometry table';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $schema->getTable('geometry')->addIndex(['geometry_subtractive'], 'geometry_subtractive');
    }

    protected function onDown(Schema $schema): void
    {
        $schema->getTable('geometry')->dropIndex('geometry_subtractive');
    }
}
