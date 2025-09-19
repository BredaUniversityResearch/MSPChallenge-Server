<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20250916081751 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Merge immersive_session_region table into immersive_session table + '.
            'Add status, and status_response fields to immersive_session_connection';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `immersive_session`
        DROP FOREIGN KEY `immersive_session_region_id`,
        DROP `region_id`,
        ADD `bottom_left_x` double NOT NULL AFTER `month`,
        ADD `bottom_left_y` double NOT NULL AFTER `bottom_left_x`,
        ADD `top_right_x` double NOT NULL AFTER `bottom_left_y`,
        ADD `top_right_y` double NOT NULL AFTER `top_right_x`
        SQL
        );
        $this->addSql('DROP TABLE `immersive_session_region`');
        $this->addSql(<<<'SQL'
        ALTER TABLE `immersive_session_connection`
        ADD `status` enum('starting','running','unresponsive') COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'starting' AFTER `session_id`,
        ADD `status_response` JSON COLLATE 'utf8mb4_bin' NULL COMMENT '(DC2Type:json_document)' AFTER `status`
        SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
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
        DROP `bottom_left_x`,
        DROP `bottom_left_y`,
        DROP `top_right_x`,
        DROP `top_right_y`,
        ADD FOREIGN KEY `immersive_session_region_id` (`region_id`) REFERENCES `immersive_session_region` (`id`)
        SQL
        );
        $this->addSql('ALTER TABLE `immersive_session_connection` DROP `status`, DROP `status_response`');
    }
}
