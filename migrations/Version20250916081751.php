<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20250916081751 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Merge immersive_session_region table into immersive_session table + '.
            'Add status, and status_response fields';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql('DELETE FROM `immersive_session`');
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
        ADD `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
        SQL
        );
        $this->addSql('DROP TABLE `immersive_session_region`');
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('DELETE FROM `immersive_session`');
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
        DROP `status`,
        DROP `status_response`,
        DROP `bottom_left_x`,
        DROP `bottom_left_y`,
        DROP `top_right_x`,
        DROP `top_right_y`,
        DROP `deleted_at`,
        DROP `created_at`,
        DROP `updated_at`,
        ADD FOREIGN KEY `immersive_session_region_id` (`region_id`) REFERENCES `immersive_session_region` (`id`)
        SQL
        );
    }
}
