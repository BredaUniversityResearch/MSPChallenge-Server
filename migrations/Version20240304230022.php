<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20240304230022 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add PolicyType and Policy entities and their relationships';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
    CREATE TABLE `policy_type` (
      `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `name` varchar(255) NOT NULL,
      `display_name` varchar(255) NOT NULL,
      `data_type` enum('boolean','ranged','temporal') NOT NULL DEFAULT 'boolean',
      `data_config` json NULL COMMENT '(DC2Type:json)',
      CONSTRAINT `name` UNIQUE (`name`)
    ) ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
    CREATE TABLE `policy` (
      `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `type_id` int(10) unsigned NOT NULL,
      `value` json NOT NULL COMMENT '(DC2Type:json)',
      FOREIGN KEY (`type_id`) REFERENCES `policy_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
    ) ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
    CREATE TABLE `plan_policy` (
      `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `plan_id` int(11) NOT NULL,
      `policy_id` int(10) unsigned NOT NULL,
      FOREIGN KEY (`plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
      FOREIGN KEY (`policy_id`) REFERENCES `policy` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
      CONSTRAINT `plan_id_policy_id` UNIQUE (`plan_id`, `policy_id`)
    ) ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
    CREATE TABLE `policy_layer` (
      `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `policy_id` int(10) unsigned NOT NULL,
      `layer_id` int(11) NOT NULL,
      FOREIGN KEY (`policy_id`) REFERENCES `policy` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
      FOREIGN KEY (`layer_id`) REFERENCES `layer` (`layer_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
      CONSTRAINT `policy_id_layer_id` UNIQUE (`policy_id`, `layer_id`),
      INDEX (`policy_id`)
    ) ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
    CREATE TABLE `policy_filter_type` (
      `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `name` varchar(255) NOT NULL,
      `field_type` enum('ascii_string','bigint','binary','blob','boolean','date','date_immutable','dateinterval','datetime','datetime_immutable','datetimetz','datetimetz_immutable','decimal','float','guid','integer','json','simple_array','smallint','string','text','time','time_immutable') NOT NULL DEFAULT 'json',
      `field_schema` json NULL COMMENT '(DC2Type:json)',
      CONSTRAINT `name` UNIQUE (`name`)
    ) ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
    CREATE TABLE `policy_filter` (
      `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `type_id` int(10) unsigned NOT NULL,
      `value` json NOT NULL COMMENT '(DC2Type:json)',
      FOREIGN KEY (`type_id`) REFERENCES `policy_filter_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
    ) ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
    CREATE TABLE `policy_filter_link` (
      `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `policy_id` int(10) unsigned NOT NULL,
      `policy_filter_id` int(10) unsigned NOT NULL,
      FOREIGN KEY (`policy_id`) REFERENCES `policy` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
      FOREIGN KEY (`policy_filter_id`) REFERENCES `policy_filter` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
      CONSTRAINT `policy_id_policy_filter_id` UNIQUE (`policy_id`, `policy_filter_id`),
      INDEX (`policy_id`)
    ) ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
    CREATE TABLE `policy_type_filter_type` (
      `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `policy_type_id` int(10) unsigned NOT NULL,
      `policy_filter_type_id` int(10) unsigned NOT NULL,
      FOREIGN KEY (`policy_type_id`) REFERENCES `policy_type` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
      FOREIGN KEY (`policy_filter_type_id`) REFERENCES `policy_filter_type` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
      CONSTRAINT `policy_type_id_policy_filter_type_id` UNIQUE (`policy_type_id`, `policy_filter_type_id`),
      INDEX (`policy_type_id`)
    ) ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
    ALTER TABLE `plan_message` DROP FOREIGN KEY `fk_plan_message_plan1`, ADD FOREIGN KEY (`plan_message_plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE CASCADE ON UPDATE NO ACTION;
    ALTER TABLE `plan_layer` DROP FOREIGN KEY `fk_plan_layer_plan1`, ADD FOREIGN KEY (`plan_layer_plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE CASCADE ON UPDATE NO ACTION;
    ALTER TABLE `approval` DROP FOREIGN KEY `fk_table1_plan2`, ADD FOREIGN KEY (`approval_plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE CASCADE ON UPDATE NO ACTION
    SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(
            <<< 'SQL'
DROP TABLE `policy_type`; DROP TABLE `policy`; DROP TABLE `plan_policy`; DROP TABLE `policy_layer`;
DROP TABLE `policy_filter_type`; DROP TABLE `policy_filter`;
ALTER TABLE `plan_message` DROP FOREIGN KEY `plan_message_ibfk_1`, ADD FOREIGN KEY (`plan_message_plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `plan_layer` DROP FOREIGN KEY `plan_layer_ibfk_1`, ADD FOREIGN KEY (`plan_layer_plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `approval` DROP FOREIGN KEY `approval_ibfk_1`, ADD FOREIGN KEY (`approval_plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
SQL
        );
    }
}
