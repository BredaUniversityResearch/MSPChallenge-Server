<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20250916081751 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Merge immersive_session_region table into immersive_session table + '.
            'Add status, status_response and docker_connection fields + '.
            'Renaming table immersive_session_connection to docker_connection + '.
            'Removing session_id field';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql('DELETE FROM `immersive_session_connection`');
        $this->addSql('DELETE FROM `immersive_session`');
        $this->addSql(<<<'SQL'
        ALTER TABLE `immersive_session_connection`
        DROP FOREIGN KEY `immersive_session_connection_session_id`,
        DROP `session_id`,
        RENAME TO `docker_connection`
        SQL
        );
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `immersive_session`
        DROP FOREIGN KEY `immersive_session_region_id`,
        DROP `region_id`,
        ADD `status` varchar(255) NOT NULL AFTER `month`,
        ADD `status_response` JSON COLLATE 'utf8mb4_bin' NULL COMMENT '(DC2Type:json_document)' AFTER `status`,
        ADD `bottom_left_x` double NOT NULL AFTER `status_response`,
        ADD `bottom_left_y` double NOT NULL AFTER `bottom_left_x`,
        ADD `top_right_x` double NOT NULL AFTER `bottom_left_y`,
        ADD `top_right_y` double NOT NULL AFTER `top_right_x`,
        ADD `deleted_at` datetime DEFAULT NULL,
        ADD `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        ADD `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        ADD `docker_connection_id` int unsigned NULL AFTER `data`,
        CHANGE `updated_at` `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
        ADD CONSTRAINT `immersive_session_docker_connection_id` FOREIGN KEY (`docker_connection_id`) REFERENCES `docker_connection` (`id`) ON DELETE SET NULL
        SQL
        );
        $this->addSql('DROP TABLE `immersive_session_region`');
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('DELETE FROM `immersive_session`');
        $this->addSql(<<<'SQL'
        ALTER TABLE `docker_connection`
        ADD `session_id` int unsigned NOT NULL AFTER `id`,
        RENAME TO `immersive_session_connection`,
        ADD CONSTRAINT `immersive_session_connection_session_id` FOREIGN KEY (`session_id`) REFERENCES `immersive_session` (`id`)
        SQL
        );
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
        ALTER TABLE `immersive_session`
        ADD `region_id` int unsigned NOT NULL AFTER `name`,
        DROP FOREIGN KEY `immersive_session_docker_connection_id`,
        DROP `docker_connection_id`,
        DROP `status`,
        DROP `status_response`,
        DROP `bottom_left_x`,
        DROP `bottom_left_y`,
        DROP `top_right_x`,
        DROP `top_right_y`,
        DROP `deleted_at`,
        DROP `created_at`,
        DROP `updated_at`,
        ADD CONSTRAINT `immersive_session_region_id` FOREIGN KEY `immersive_session_region_id` (`region_id`) REFERENCES `immersive_session_region` (`id`)
        SQL
        );
    }
}
