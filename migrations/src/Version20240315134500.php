<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20240315134500 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Drops game_list unique key on save_id in Server Manager';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE `game_list` DROP FOREIGN KEY `FK_AFDD9434602EC74B`');
        $this->addSql('ALTER TABLE `game_list` DROP INDEX `UNIQ_AFDD9434602EC74B`');
        $this->addSql('ALTER TABLE `game_list` ADD FOREIGN KEY `game_list_game_save` (`save_id`) REFERENCES `game_saves` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE `game_list` DROP FOREIGN KEY `game_list_game_save`');
        $this->addSql('ALTER TABLE `game_list` ADD UNIQUE INDEX UNIQ_AFDD9434602EC74B (save_id)');
        $this->addSql('ALTER TABLE game_list ADD CONSTRAINT FK_AFDD9434602EC74B FOREIGN KEY (save_id) REFERENCES game_saves (id)');
    }
}
