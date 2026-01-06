<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20240130173300 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Alteration of game session database plan_layer table to make Doctrine Entity work';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    /**
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function onUp(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `geometry` CHANGE geometry_subtractive geometry_subtractive INT(11) NULL'
        );
        $this->addSql(
            'ALTER TABLE `plan_delete` ADD `plan_delete_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
        );
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            'ALTER TABLE `plan_restriction_area` ADD `plan_restriction_area_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
        );
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            'ALTER TABLE `energy_connection` ADD `energy_connection_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST'
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `geometry` CHANGE geometry_subtractive geometry_subtractive INT(11) NOT NULL DEFAULT 0'
        );
        $this->addSql('ALTER TABLE `plan_delete` DROP IF EXISTS `plan_delete_id`;');
        $this->addSql('ALTER TABLE `plan_restriction_area` DROP IF EXISTS `plan_restriction_area_id`;');
        $this->addSql('ALTER TABLE `energy_connection` DROP IF EXISTS `energy_connection_id`;');
    }
}
