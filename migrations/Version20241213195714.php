<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241213195714 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Change time column type of event_log table from timestamp to datetime. '.
            'Change event_log_source from 75 to 255 characters. '.
            'Also add new columns: event_log_reference_object, event_log_reference_id.';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `event_log`
        CHANGE `event_log_time` `event_log_time` datetime NOT NULL AFTER `event_log_id`,
        CHANGE `event_log_source` `event_log_source` varchar(255) COLLATE 'utf8mb4_general_ci' NOT NULL COMMENT 'What triggered this (Server, MEL, SEL, CEL, Game)?' AFTER `event_log_time`,
        ADD `event_log_reference_object` varchar(255) COLLATE 'utf8mb4_general_ci' NULL,
        ADD `event_log_reference_id` int unsigned NULL AFTER `event_log_reference_object`
        SQL
        );
        $this->addSql(<<<'SQL'
        ALTER TABLE `event_log`
        ADD INDEX `event_log_reference_object` (`event_log_reference_object`),
        ADD INDEX `event_log_reference_id` (`event_log_reference_id`),
        ADD INDEX `event_log_source` (`event_log_source`)
        SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE `event_log`
        DROP INDEX `event_log_source`        
        SQL
        );
        $this->addSql(<<<'SQL'
        ALTER TABLE `event_log`
        CHANGE `event_log_time` `event_log_time` timestamp NOT NULL AFTER `event_log_id`,
        CHANGE `event_log_source` `event_log_source` varchar(75) COLLATE 'utf8mb4_general_ci' NOT NULL COMMENT 'What triggered this (Server, MEL, SEL, CEL, Game)?' AFTER `event_log_time`,
        DROP `event_log_reference_object`,
        DROP `event_log_reference_id`
        SQL
        );
    }
}
