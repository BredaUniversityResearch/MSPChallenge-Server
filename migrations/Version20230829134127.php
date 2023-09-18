<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230829134127 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'insert new config for Eastern med Sea';
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
(6, 'Eastern_Med_Sea_5km_basic', 'Eastern Mediterranean Sea basic with 5km cell size configuration file supplied by BUas'),
(7, 'Eastern_Med_Sea_10km_basic', 'Eastern Mediterranean Sea basic with 10km cell size configuration file supplied by BUas');
SQL
        );

        $this->addSql(<<<'SQL'
INSERT INTO `game_config_version` (`id`, `game_config_files_id`, `version`, `version_message`, `visibility`, `upload_time`, `upload_user`, `last_played_time`, `file_path`, `region`, `client_versions`) VALUES
(6, 6, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Eastern_Med_Sea_5km_basic/Eastern_Med_Sea_5km_basic_1.json', 'easternmed', 'Any'),
(7, 7, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Eastern_Med_Sea_10km_basic/Eastern_Med_Sea_10km_basic_1.json', 'easternmed', 'Any');
SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DELETE FROM `game_config_version` WHERE `id` IN (6,7);
SQL
        );

        $this->addSql(<<<'SQL'
DELETE FROM `game_config_files` WHERE `id` IN (6,7);
SQL
        );
    }
}
