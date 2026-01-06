<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20230320211248 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add unique column api_batch_guid to api_batch table';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql("ALTER TABLE `api_batch` ADD `api_batch_guid` char(36) COLLATE 'utf8mb4_general_ci' NULL AFTER `api_batch_user_id`");
        $this->addSql("ALTER TABLE `api_batch` ADD UNIQUE `api_batch_guid` (`api_batch_guid`)");
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `api_batch` DROP INDEX IF EXISTS `api_batch_guid`");
        $this->addSql("ALTER TABLE `api_batch` DROP IF EXISTS `api_batch_guid`");
    }
}
