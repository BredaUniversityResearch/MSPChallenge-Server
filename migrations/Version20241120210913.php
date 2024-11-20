<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20241120210913 extends MSPMigration
{
    public function getDescription(): string
    {
         return 'Insert new config for Eastern med Sea';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
INSERT INTO `game_config_files` (`id`, `filename`, `description`) VALUES
(7, 'Eastern_Med_Sea_basic', 'Eastern Mediterranean Sea basic configuration file supplied by BUas')
SQL
        );
        $this->addSql(<<<'SQL'
INSERT INTO `game_config_version` (`id`, `game_config_files_id`, `version`, `version_message`, `visibility`, `upload_time`, `upload_user`, `last_played_time`, `file_path`, `region`, `client_versions`) VALUES
(7, 7, 1, 'See www.mspchallenge.info', 'active', unix_timestamp(), 1, 0, 'Eastern_Med_Sea_basic/Eastern_Med_Sea_basic_1.json', 'easternmed', 'Any')
SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DELETE FROM `game_config_version` WHERE `id`=7
SQL
        );
        $this->addSql(<<<'SQL'
DELETE FROM `game_config_files` WHERE `id`=7
SQL
        );
    }
}
