<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240415074653 extends MSPMigration
{
    public function getDescription(): string
    {
        return '';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
ALTER TABLE `layer`
CHANGE `layer_geotype` `layer_geotype` varchar(75) COLLATE 'utf8mb4_general_ci' NULL DEFAULT 'NULL' AFTER `layer_name`;
UPDATE `layer` SET `layer_geotype`=NULL WHERE `layer_geotype`='';
ALTER TABLE `layer`
CHANGE `layer_geotype` `layer_geotype` enum('polygon','point','raster','line') COLLATE 'utf8mb4_general_ci' NULL DEFAULT NULL AFTER `layer_name`
SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(
            <<< 'SQL'
ALTER TABLE `layer`
CHANGE `layer_geotype` `layer_geotype` varchar(75) COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `layer_name`
SQL
        );
    }
}
