<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20250312220909 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Remove unique index uq_geometry_data from geometry table';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `geometry` DROP INDEX `uq_geometry_data`');
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql('CREATE UNIQUE INDEX `uq_geometry_data` ON `geometry` (`geometry_geometry`, `geometry_data`, `geometry_layer_id`)');
    }
}
