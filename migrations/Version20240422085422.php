<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240422085422 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add "EEZ bans" policy and "EEZ" filter type. Set "fleet" filter field type to "bigint". Removed data type "temporal" from policy type.';
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
            ALTER TABLE `policy_type` CHANGE `data_type` `data_type` enum('none','boolean','ranged') COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'none' AFTER `display_name`;
            UPDATE `policy_type` SET `data_type` = 'none'  WHERE `id` = '2';
            UPDATE `policy_filter_type` SET `field_type` = 'bigint' WHERE `id` = '1';
            INSERT INTO `policy_filter_type` (`id`, `name`, `field_type`) VALUES (3, 'EEZ', 'bigint');
            INSERT INTO `policy_type` (`id`, `name`, `display_name`) VALUES (4, 'eez_bans', 'EEZ bans');
            INSERT INTO `policy_type_filter_type` (`id`,`policy_type_id`, `policy_filter_type_id`) VALUES (4,4,3);
            SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
            DELETE FROM `policy_type_filter_type` WHERE `id` = '4';
            DELETE FROM `policy_type` WHERE `id` = '4';
            DELETE FROM `policy_filter_type` WHERE `id` = '3';
            UPDATE `policy_filter_type` SET `field_type` = 'smallint' WHERE `id` = '1';
            ALTER TABLE `policy_type` CHANGE `data_type` `data_type` enum('boolean','ranged','temporal') COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'none' AFTER `display_name`;
            UPDATE `policy_type` SET `data_type` = 'temporal'  WHERE `id` = '2';
            SQL
        );
    }
}
