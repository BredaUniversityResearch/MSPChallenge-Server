<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250410181450 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Create immersive_session, immersive_session_connection and immersive_session_region tables';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        CREATE TABLE `immersive_session_region` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `bottom_left_x` double NOT NULL,
          `bottom_left_y` double NOT NULL,
          `top_right_x` double NOT NULL,
          `top_right_y` double NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL
        );
        $this->addSql(<<<'SQL'
        CREATE TABLE `immersive_session` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `region_id` int unsigned NOT NULL,
          `name` varchar(255) NOT NULL,
          `type` enum('mixed-reality') NOT NULL DEFAULT 'mixed-reality',
          `month` int NOT NULL DEFAULT -1,
          `data` JSON DEFAULT NULL COMMENT '(DC2Type:json_document)',
          PRIMARY KEY (`id`),
          KEY `immersive_session_region_id` (`region_id`),
          CONSTRAINT `immersive_session_region_id` FOREIGN KEY (`region_id`) REFERENCES `immersive_session_region` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci         
        SQL
        );
        $this->addSql(<<<'SQL'
        CREATE TABLE `immersive_session_connection` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `session_id` int unsigned NOT NULL,
          `docker_api_id` int unsigned NOT NULL,
          `port` int unsigned NOT NULL,
          `docker_container_id` varchar(255) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `docker_api_id_port` (`docker_api_id`,`port`),
          UNIQUE KEY `docker_container_id` (`docker_container_id`),
          KEY `immersive_session_connection_session_id` (`session_id`),
          CONSTRAINT `immersive_session_connection_session_id` FOREIGN KEY (`session_id`) REFERENCES `immersive_session` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE immersive_session DROP FOREIGN KEY immersive_session_region_id');
        $this->addSql('ALTER TABLE immersive_session_connection DROP FOREIGN KEY immersive_session_connection_session_id');
        $this->addSql('DROP TABLE immersive_session_region');
        $this->addSql('DROP TABLE immersive_session');
        $this->addSql('DROP TABLE immersive_session_connection');
    }
}
