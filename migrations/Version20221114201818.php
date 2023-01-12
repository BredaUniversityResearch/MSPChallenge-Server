<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221114201818 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Install ServerManager database';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        if ($schema->hasTable('game_config_files') ||
            $schema->hasTable('game_config_version') ||
            $schema->hasTable('game_geoservers') ||
            $schema->hasTable('game_list') ||
            $schema->hasTable('game_saves') ||
            $schema->hasTable('game_servers') ||
            $schema->hasTable('game_watchdog_servers') ||
            $schema->hasTable('settings') ||
            $schema->hasTable('users')) {
            $this->write('Detected existing tables. Assuming database has been already installed');
            return;
        }

        $this->addSql('CREATE TABLE game_config_files (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(45) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_config_version (id INT AUTO_INCREMENT NOT NULL, game_config_files_id INT NOT NULL, version INT NOT NULL, version_message LONGTEXT DEFAULT NULL, visibility VARCHAR(255) NOT NULL, upload_time BIGINT NOT NULL, upload_user INT NOT NULL, last_played_time BIGINT NOT NULL, file_path VARCHAR(255) NOT NULL, region VARCHAR(45) NOT NULL, client_versions VARCHAR(45) NOT NULL, INDEX IDX_10CA4D7F619E7216 (game_config_files_id), UNIQUE INDEX uq_game_config_version (game_config_files_id, version), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_geoservers (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(128) NOT NULL, address VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, available SMALLINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_F3B4ECE7D4E6F81 (address), UNIQUE INDEX UNIQ_F3B4ECE7F85E0677 (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_list (id INT AUTO_INCREMENT NOT NULL, game_config_version_id INT NOT NULL, game_server_id INT NOT NULL, game_geoserver_id INT NOT NULL, watchdog_server_id INT NOT NULL, save_id INT DEFAULT NULL, name VARCHAR(128) NOT NULL, game_creation_time BIGINT NOT NULL, game_start_year INT NOT NULL, game_end_month INT NOT NULL, game_current_month INT NOT NULL, game_running_til_time BIGINT NOT NULL, password_admin LONGTEXT NOT NULL, password_player LONGTEXT NOT NULL, session_state VARCHAR(255) NOT NULL, game_state VARCHAR(255) NOT NULL, game_visibility VARCHAR(255) NOT NULL, players_active INT DEFAULT NULL, players_past_hour INT DEFAULT NULL, demo_session SMALLINT NOT NULL, api_access_token VARCHAR(32) NOT NULL, server_version VARCHAR(45) NOT NULL, INDEX IDX_AFDD943478AF0AB (game_config_version_id), INDEX IDX_AFDD9434CAE227B5 (game_server_id), INDEX IDX_AFDD943436EDE018 (game_geoserver_id), INDEX IDX_AFDD94347BF63E1 (watchdog_server_id), UNIQUE INDEX UNIQ_AFDD9434602EC74B (save_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_saves (id INT AUTO_INCREMENT NOT NULL, game_config_version_id INT NOT NULL, game_server_id INT NOT NULL, watchdog_server_id INT NOT NULL, name VARCHAR(128) NOT NULL, game_config_files_filename VARCHAR(45) NOT NULL, game_config_versions_region VARCHAR(45) NOT NULL, game_creation_time BIGINT NOT NULL, game_start_year INT NOT NULL, game_end_month INT NOT NULL, game_current_month INT NOT NULL, game_running_til_time BIGINT NOT NULL, password_admin LONGTEXT NOT NULL, password_player LONGTEXT NOT NULL, session_state VARCHAR(255) NOT NULL, game_state VARCHAR(255) NOT NULL, game_visibility VARCHAR(255) NOT NULL, players_active INT DEFAULT NULL, players_past_hour INT DEFAULT NULL, demo_session SMALLINT NOT NULL, api_access_token VARCHAR(32) NOT NULL, save_type VARCHAR(255) NOT NULL, save_notes LONGTEXT NOT NULL, save_visibility VARCHAR(255) NOT NULL, save_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL, server_version VARCHAR(45) NOT NULL, INDEX IDX_48032F6D78AF0AB (game_config_version_id), INDEX IDX_48032F6DCAE227B5 (game_server_id), INDEX IDX_48032F6D7BF63E1 (watchdog_server_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_servers (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(128) NOT NULL, address VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_watchdog_servers (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(128) NOT NULL, address VARCHAR(255) NOT NULL, available SMALLINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_C35754DF5E237E06 (name), UNIQUE INDEX UNIQ_C35754DFD4E6F81 (address), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, value LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) NOT NULL, pin VARCHAR(255) DEFAULT NULL, account_owner SMALLINT DEFAULT 0 NOT NULL, account_id INT DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE game_config_version ADD CONSTRAINT FK_10CA4D7F619E7216 FOREIGN KEY (game_config_files_id) REFERENCES game_config_files (id)');
        $this->addSql('ALTER TABLE game_list ADD CONSTRAINT FK_AFDD943478AF0AB FOREIGN KEY (game_config_version_id) REFERENCES game_config_version (id)');
        $this->addSql('ALTER TABLE game_list ADD CONSTRAINT FK_AFDD9434CAE227B5 FOREIGN KEY (game_server_id) REFERENCES game_servers (id)');
        $this->addSql('ALTER TABLE game_list ADD CONSTRAINT FK_AFDD943436EDE018 FOREIGN KEY (game_geoserver_id) REFERENCES game_geoservers (id)');
        $this->addSql('ALTER TABLE game_list ADD CONSTRAINT FK_AFDD94347BF63E1 FOREIGN KEY (watchdog_server_id) REFERENCES game_watchdog_servers (id)');
        $this->addSql('ALTER TABLE game_list ADD CONSTRAINT FK_AFDD9434602EC74B FOREIGN KEY (save_id) REFERENCES game_saves (id)');
        $this->addSql('ALTER TABLE game_saves ADD CONSTRAINT FK_48032F6D78AF0AB FOREIGN KEY (game_config_version_id) REFERENCES game_config_version (id)');
        $this->addSql('ALTER TABLE game_saves ADD CONSTRAINT FK_48032F6DCAE227B5 FOREIGN KEY (game_server_id) REFERENCES game_servers (id)');
        $this->addSql('ALTER TABLE game_saves ADD CONSTRAINT FK_48032F6D7BF63E1 FOREIGN KEY (watchdog_server_id) REFERENCES game_watchdog_servers (id)');

        $this->addSql(<<<'SQL'
  INSERT INTO `game_servers` (`id`, `name`, `address`) VALUES
  (1, 'Default: the server machine', 'caddy');
SQL
        );

        $this->addSql(<<<'SQL'
  INSERT INTO `game_watchdog_servers` (`id`, `name`, `address`) VALUES
  (1, 'Default: the same server machine', 'caddy');
SQL
        );

        $this->addSql(<<<'SQL'
  INSERT INTO `game_config_files` (`id`, `filename`, `description`) VALUES
  (1, 'North_Sea_basic', 'North Sea basic configuration file supplied by BUas'),
  (2, 'Baltic_Sea_basic', 'Baltic Sea basic configuration file supplied by BUas'),
  (3, 'Clyde_marine_region_basic', 'Clyde marine region basic configuration file supplied by BUas'),
  (4, 'North_Sea_Digitwin_basic', 'North Sea Digitwin basic configuration file supplied by BUas'),
  (5, 'Adriatic_Sea_basic', 'Adriatic Sea basic configuration file supplied by BUas');
SQL
        );

        $this->addSql(<<<'SQL'
  INSERT INTO `game_config_version` (`id`, `game_config_files_id`, `version`, `version_message`, `visibility`, `upload_time`, `upload_user`, `last_played_time`, `file_path`, `region`, `client_versions`) VALUES
  (1, 1, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'North_Sea_basic/North_Sea_basic_1.json', 'northsee', 'Any'),
  (2, 2, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Baltic_Sea_basic/Baltic_Sea_basic_1.json', 'balticline', 'Any'),
  (3, 3, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Clyde_marine_region_basic/Clyde_marine_region_basic_1.json', 'simcelt', 'Any'),
  (4, 4, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'North_Sea_Digitwin_basic/North_Sea_Digitwin_basic_1.json', 'northsee', 'Any'),
  (5, 5, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Adriatic_Sea_basic/Adriatic_Sea_basic_1.json', 'adriatic', 'Any');
SQL
        );

        $this->addSql(<<<'SQL'
  INSERT INTO `game_geoservers` (`id`, `name`, `address`, `username`, `password`) VALUES ('1', 'Default: the public MSP Challenge GeoServer', 'https://geo.mspchallenge.info/geoserver/', 'YXV0b21hdGljYWxseW9idGFpbmVk', 'YXV0b21hdGljYWxseW9idGFpbmVk');
SQL
        );

        $this->addSql(<<<'SQL'
  INSERT INTO `settings` (`name`, `value`) VALUES 
  ('migration_20200618.php', 'Never'),
  ('migration_20200721.php', 'Never'),
  ('migration_20200901.php', 'Never'),
  ('migration_20200917.php', 'Never'),
  ('migration_20200930.php', 'Never'),
  ('migration_20201002.php', 'Never'),
  ('migration_20201104.php', 'Never'),
  ('migration_20201130.php', 'Never'),
  ('migration_20210211.php', 'Never'),
  ('migration_20210325.php', 'Never'),
  ('migration_20210413.php', 'Never'),
  ('migration_20210531.php', 'Never'),
  ('migration_20211004.php', 'Never');
SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_config_version DROP FOREIGN KEY FK_10CA4D7F619E7216');
        $this->addSql('ALTER TABLE game_list DROP FOREIGN KEY FK_AFDD943478AF0AB');
        $this->addSql('ALTER TABLE game_list DROP FOREIGN KEY FK_AFDD9434CAE227B5');
        $this->addSql('ALTER TABLE game_list DROP FOREIGN KEY FK_AFDD943436EDE018');
        $this->addSql('ALTER TABLE game_list DROP FOREIGN KEY FK_AFDD94347BF63E1');
        $this->addSql('ALTER TABLE game_list DROP FOREIGN KEY FK_AFDD9434602EC74B');
        $this->addSql('ALTER TABLE game_saves DROP FOREIGN KEY FK_48032F6D78AF0AB');
        $this->addSql('ALTER TABLE game_saves DROP FOREIGN KEY FK_48032F6DCAE227B5');
        $this->addSql('ALTER TABLE game_saves DROP FOREIGN KEY FK_48032F6D7BF63E1');
        $this->addSql('DROP TABLE game_config_files');
        $this->addSql('DROP TABLE game_config_version');
        $this->addSql('DROP TABLE game_geoservers');
        $this->addSql('DROP TABLE game_list');
        $this->addSql('DROP TABLE game_saves');
        $this->addSql('DROP TABLE game_servers');
        $this->addSql('DROP TABLE game_watchdog_servers');
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE users');
    }
}
