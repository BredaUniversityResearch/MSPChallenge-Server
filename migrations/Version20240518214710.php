<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240518214710 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Create policy entities and their relationships incl. default data';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
            CREATE TABLE plan_policy (id INT AUTO_INCREMENT NOT NULL, plan_id INT NOT NULL, policy_id INT NOT NULL, INDEX `idx_plan_policy_plan_id` (plan_id), INDEX `idx_plan_policy_policy_id` (policy_id), UNIQUE INDEX plan_id_policy_id (plan_id, policy_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            CREATE TABLE policy (id INT AUTO_INCREMENT NOT NULL, type_id INT NOT NULL, data JSON DEFAULT NULL COMMENT '(DC2Type:json)', INDEX `idx_policy_type_id` (type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            CREATE TABLE policy_filter_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, `schema` JSON DEFAULT NULL COMMENT '(DC2Type:json)', UNIQUE INDEX name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            CREATE TABLE policy_layer (id INT AUTO_INCREMENT NOT NULL, policy_id INT NOT NULL, layer_id INT NOT NULL, INDEX `idx_policy_layer_policy_id` (policy_id), INDEX `idx_policy_layer_layer_id` (layer_id), UNIQUE INDEX policy_id_layer_id (policy_id, layer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            CREATE TABLE policy_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, display_name VARCHAR(255) NOT NULL, `schema` JSON DEFAULT NULL COMMENT '(DC2Type:json)', UNIQUE INDEX name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            CREATE TABLE policy_type_filter_type (id INT AUTO_INCREMENT NOT NULL, policy_type_id INT NOT NULL, policy_filter_type_id INT NOT NULL, INDEX `idx_policy_type_filter_type_policy_type_id` (policy_type_id), INDEX `idx_policy_type_filter_type_policy_filter_type_id` (policy_filter_type_id), UNIQUE INDEX policy_type_id_policy_filter_type_id (policy_type_id, policy_filter_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            ALTER TABLE `plan_policy` ADD CONSTRAINT `fk_plan_policy_plan_id` FOREIGN KEY (`plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `plan_policy` ADD CONSTRAINT `fk_plan_policy_policy_id` FOREIGN KEY (`policy_id`) REFERENCES `policy` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `policy` ADD CONSTRAINT `fk_policy_type_id` FOREIGN KEY (`type_id`) REFERENCES `policy_type` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `policy_layer` ADD CONSTRAINT `fk_policy_layer_policy_id` FOREIGN KEY (`policy_id`) REFERENCES `policy` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `policy_layer` ADD CONSTRAINT `fk_policy_layer_layer_id` FOREIGN KEY (`layer_id`) REFERENCES `layer` (`layer_id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `policy_type_filter_type` ADD CONSTRAINT `fk_policy_type_filter_type_policy_type_id` FOREIGN KEY (`policy_type_id`) REFERENCES `policy_type` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `policy_type_filter_type` ADD CONSTRAINT `fk_policy_type_filter_type_policy_filter_type_id` FOREIGN KEY (`policy_filter_type_id`) REFERENCES `policy_filter_type` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            REPLACE INTO `policy_filter_type` (`id`, `name`, `schema`) VALUES
            (1,	'fleet',	'{\r\n  \"type\": \"object\",\r\n  \"properties\": {\r\n    \"fleets\": {\r\n      \"type\": \"integer\"\r\n    }\r\n  },\r\n  \"required\": [\"fleets\"]\r\n}'),
            (2,	'schedule',	'{\r\n  \"type\": \"object\",\r\n  \"properties\": {\r\n    \"months\": {\r\n      \"type\": \"array\",\r\n      \"items\": {\r\n        \"type\": \"integer\"\r\n      }\r\n    }\r\n  },\r\n  \"required\": [\"months"]\r\n}');    
            REPLACE INTO `policy_type` (`id`, `name`, `display_name`, `schema`) VALUES
            (1,	'buffer_zone',	'Buffer zone',	'{\r\n  \"type\": \"object\",\r\n  \"properties\": {\r\n    \"radius\": {\r\n      \"type\": \"number\",\r\n      \"default\": 40000\r\n    }\r\n  },\r\n  \"required\": [\"radius\"]\r\n}'),
            (2,	'seasonal_closure',	'Seasonal closure',	NULL),
            (3,	'eco_gear',	'Ecological fishing gear',	'{\r\n  \"type\": \"object\",\r\n  \"properties\": {\r\n    \"per_country\": {\r\n      \"type\": \"object\",\r\n      \"additionalProperties\": false,\r\n      \"patternProperties\": {\r\n        \"^[0-9]+$\": {\r\n          \"type\": \"boolean\"\r\n        }\r\n      }\r\n    }\r\n  },\r\n  \"required\": [\"per_country\"]\r\n}\r\n');
            REPLACE INTO `policy_type_filter_type` (`id`, `policy_type_id`, `policy_filter_type_id`) VALUES
            (1,	1,	1),
            (2,	2,	1),
            (3,	2,	2),
            (4,	1,	2)
            SQL
        );

        // add policy plan related cascading deletes
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $succeeded = false;
        foreach ($schema->getTable('plan_layer')->getForeignKeys() as $fk) {
            if ('plan_layer_plan_id' == ($fk->getLocalColumns()[0] ?? '')) {
                $this->addSql('ALTER TABLE `plan_layer` DROP FOREIGN KEY `'.$fk->getName().'`, ADD CONSTRAINT `fk_plan_player_plan_layer_plan_id` FOREIGN KEY (`plan_layer_plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE CASCADE ON UPDATE NO ACTION');
                $succeeded = true;
                break;
            }
        }
        $this->abortIf(!$succeeded, 'Could not find foreign key plan_layer_plan_id for table plan_layer');

        $succeeded = false;
        foreach ($schema->getTable('plan_message')->getForeignKeys() as $fk) {
            if ('plan_message_plan_id' == ($fk->getLocalColumns()[0] ?? '')) {
                $this->addSql('ALTER TABLE `plan_message` DROP FOREIGN KEY `'.$fk->getName().'`, ADD CONSTRAINT `fk_plan_message_plan_message_plan_id` FOREIGN KEY (`plan_message_plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE CASCADE ON UPDATE NO ACTION');
                $succeeded = true;
                break;
            }
        }
        $this->abortIf(!$succeeded, 'Could not find foreign key plan_message_plan_id for table plan_message');
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql("TRUNCATE TABLE `policy_type_filter_type`; TRUNCATE TABLE `policy_type`; TRUNCATE TABLE `policy_filter_type`");
        $this->addSql('ALTER TABLE plan_policy DROP FOREIGN KEY FK_44251D64E899029B');
        $this->addSql('ALTER TABLE plan_policy DROP FOREIGN KEY FK_44251D642D29E3C6');
        $this->addSql('ALTER TABLE policy DROP FOREIGN KEY FK_F07D0516C54C8C93');
        $this->addSql('ALTER TABLE policy_layer DROP FOREIGN KEY FK_5F1DA8E12D29E3C6');
        $this->addSql('ALTER TABLE policy_layer DROP FOREIGN KEY FK_5F1DA8E1EA6EFDCD');
        $this->addSql('ALTER TABLE policy_type_filter_type DROP FOREIGN KEY FK_5F4E9511A66034A7');
        $this->addSql('ALTER TABLE policy_type_filter_type DROP FOREIGN KEY FK_5F4E9511F5CB7147');
        $this->addSql('DROP TABLE plan_policy');
        $this->addSql('DROP TABLE policy');
        $this->addSql('DROP TABLE policy_filter_type');
        $this->addSql('DROP TABLE policy_layer');
        $this->addSql('DROP TABLE policy_type');
        $this->addSql('DROP TABLE policy_type_filter_type');
    }
}
