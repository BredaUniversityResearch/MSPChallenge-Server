<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20230209151519 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add column api_batch_lastupdate to api_batch';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `api_batch` ADD `api_batch_lastupdate` double NOT NULL DEFAULT '0'");
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `api_batch` DROP IF EXISTS `api_batch_lastupdate`');
    }
}
