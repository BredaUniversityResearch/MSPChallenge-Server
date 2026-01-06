<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20240117213025 extends MSPMigration
{
    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    public function getDescription(): string
    {
        return 'Add layer_tags column to layer table';
    }

    public function onUp(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `layer` ADD `layer_tags` text COLLATE 'utf8mb4_general_ci' NULL");
    }

    public function onDown(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `layer` DROP IF EXISTS `layer_tags`");
    }
}
