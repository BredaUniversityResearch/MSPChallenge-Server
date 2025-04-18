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
        $this->addSql("INSERT INTO `game_config_files` (`filename`, `description`) VALUES ('Eastern_Med_Sea_basic', 'Eastern Mediterranean Sea basic configuration file supplied by BUas')");
        $this->addSql("
            INSERT INTO `game_config_version` (
                `game_config_files_id`, `version`, `version_message`, `visibility`, `upload_time`, `upload_user`, `last_played_time`, `file_path`, `region`, `client_versions`
            )
            SELECT 
                `id`, 1, 'See www.mspchallenge.info', 'active', UNIX_TIMESTAMP(), 1, 0, 'Eastern_Med_Sea_basic/Eastern_Med_Sea_basic_1.json', 'easternmed', 'Any'
            FROM 
                `game_config_files`
            WHERE 
                `filename` = 'Eastern_Med_Sea_basic'
        ");
    }

    protected function onDown(Schema $schema): void
    {
        // Delete dependent records in game_list
        $this->addSql(<<<'SQL'
DELETE FROM `game_list` WHERE `game_config_version_id` IN (SELECT id FROM `game_config_version` WHERE `game_config_files_id` IN (SELECT id FROM `game_config_files` WHERE `filename` = 'Eastern_Med_Sea_basic'))
SQL
        );

        $this->addSql(<<<'SQL'
DELETE FROM `game_config_version` WHERE `game_config_files_id` IN (SELECT id FROM `game_config_files` WHERE `filename` = 'Eastern_Med_Sea_basic')
SQL
        );
        $this->addSql(<<<'SQL'
DELETE FROM `game_config_files` WHERE `filename` = 'Eastern_Med_Sea_basic'
SQL
        );
    }
}
