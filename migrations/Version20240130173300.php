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

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    /**
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function onUp(Schema $schema): void
    {
        $sql = 'ALTER TABLE IF EXISTS `plan_delete` ADD `plan_delete_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;';
        $this->addSql($sql);
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE IF EXISTS `plan_delete` DROP `plan_delete_id`;');
    }
}
