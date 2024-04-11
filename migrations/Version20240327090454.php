<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240327090454  extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add first default policy type and filter type data. Nullable policy value.';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);

    }

    protected function onUp(Schema $schema): void
    {
        // using replace instead of insert to prevent any conflicting data from previous migration versions, of which the inserts have moved here
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
    ALTER TABLE `policy`
    CHANGE `value` `value` longtext COLLATE 'utf8mb4_bin' NULL DEFAULT 'NULL' COMMENT '(DC2Type:json)' AFTER `type_id`;    
    REPLACE INTO `policy_filter_type` (`id`, `name`, `field_type`, `field_schema`) VALUES
    (1,	'fleet',	'smallint',	NULL),
    (2,	'schedule',	'json',	'{\r\n  \"$schema\": \"http://json-schema.org/draft-04/schema#\",\r\n  \"type\": \"array\",\r\n  \"items\": [\r\n    {\r\n      \"type\": \"integer\"\r\n    }\r\n  ]\r\n}');
    REPLACE INTO `policy_type` (`id`, `name`, `display_name`, `data_type`, `data_config`) VALUES
    (1,	'buffer',	'Buffer zone',	'ranged',	'{\"min\":0,\"unit_step_size\":10000,\"max\":100000}'),
    (2,	'seasonal_closures',	'Seasonal closures',	'temporal',	NULL),
    (3,	'ecological_fishing_gear',	'Ecological fishing gear',	'boolean', NULL);
    REPLACE INTO `policy_type_filter_type` (`id`, `policy_type_id`, `policy_filter_type_id`) VALUES
    (1,	1,	1),
    (2,	2,	1),
    (3,	2,	2);
    SQL
        );

    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
    TRUNCATE TABLE `policy_type_filter_type`; TRUNCATE TABLE `policy_type`; TRUNCATE TABLE `policy_filter_type`;
    ALTER TABLE `policy`
    CHANGE `value` `value` longtext COLLATE 'utf8mb4_bin' NOT NULL COMMENT '(DC2Type:json)' AFTER `type_id`;
    SQL
        );
    }
}
