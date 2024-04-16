<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240416142355 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add western baltic sea basin configuration';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
            INSERT INTO `game_config_files` (`id`, `filename`, `description`) VALUES
            (6,	'Western_Baltic_Sea_basic',	'Western Baltic Sea basic configuration file supplied by BUas');
            
            INSERT INTO `game_config_version` (`id`, `game_config_files_id`, `version`, `version_message`, `visibility`, `upload_time`, `upload_user`, `last_played_time`, `file_path`, `region`, `client_versions`) VALUES
            (6,	6,	1,	'See www.mspchallenge.info',	'active',	1713277131,	1,	0,	'Western_Baltic_Sea_basic/Western_Baltic_Sea_basic_1.json',	'balticline',	'Any');
SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(
            <<< 'SQL'
            DELETE FROM `game_config_version` WHERE `id` = 6;
            DELETE FROM `game_config_files` WHERE `id` = 6;
SQL
        );
    }
}
