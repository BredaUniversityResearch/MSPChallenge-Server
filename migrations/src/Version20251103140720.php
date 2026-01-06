<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20251103140720 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'For table warning make column warning_restriction_id nullable, and add custom_restriction_id column. Also remove nullable for columns that should not be nullable';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE `warning` DROP FOREIGN KEY `fk_warning_restriction1`;
        ALTER TABLE `warning`
        CHANGE `warning_restriction_id` `warning_restriction_id` int NULL DEFAULT NULL AFTER `warning_source_plan_id`,
        CHANGE `warning_last_update` `warning_last_update` double NOT NULL AFTER `warning_id`,
        CHANGE `warning_active` `warning_active` tinyint NOT NULL DEFAULT '1' AFTER `warning_last_update`,
        CHANGE `warning_issue_type` `warning_issue_type` enum('Error','Warning','Info','None') NOT NULL DEFAULT 'None' AFTER `warning_layer_id`,
        CHANGE `warning_x` `warning_x` float NOT NULL AFTER `warning_issue_type`,
        CHANGE `warning_y` `warning_y` float NOT NULL AFTER `warning_x`,
        ADD `custom_restriction_id` int unsigned NULL DEFAULT NULL,
        ADD INDEX `custom_restriction_id` (`custom_restriction_id`);
        ALTER TABLE `warning`
        ADD FOREIGN KEY `fk_warning_restriction1` (`warning_restriction_id`) REFERENCES `restriction` (`restriction_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
        SQL);
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `warning`
        CHANGE `warning_last_update` `warning_last_update` double NULL AFTER `warning_id`,
        CHANGE `warning_active` `warning_active` tinyint NULL DEFAULT '1' AFTER `warning_last_update`,
        CHANGE `warning_issue_type` `warning_issue_type` enum('Error','Warning','Info','None') NULL DEFAULT 'None' AFTER `warning_layer_id`,
        CHANGE `warning_x` `warning_x` float NULL AFTER `warning_issue_type`,
        CHANGE `warning_y` `warning_y` float NULL AFTER `warning_x`,
        CHANGE `warning_restriction_id` `warning_restriction_id` int NOT NULL AFTER `warning_source_plan_id`,
        DROP INDEX `custom_restriction_id`,
        DROP `custom_restriction_id`
        SQL);
    }
}
