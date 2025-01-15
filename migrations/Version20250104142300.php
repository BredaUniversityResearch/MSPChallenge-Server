<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250104142300 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Drops erroneous unique key on username in Server Manager GameGeoServer';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE `game_geoservers` DROP INDEX `UNIQ_F3B4ECE7F85E0677`');
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE `game_geoservers` ADD UNIQUE INDEX UNIQ_F3B4ECE7F85E0677 (username)');
    }
}
