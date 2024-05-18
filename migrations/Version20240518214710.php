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
        $this->addSql('CREATE TABLE plan_policy (id INT AUTO_INCREMENT NOT NULL, plan_id INT NOT NULL, policy_id INT NOT NULL, INDEX IDX_44251D64E899029B (plan_id), INDEX IDX_44251D642D29E3C6 (policy_id), UNIQUE INDEX plan_id_policy_id (plan_id, policy_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE policy (id INT AUTO_INCREMENT NOT NULL, type_id INT NOT NULL, data JSON DEFAULT \'NULL\' COMMENT \'(DC2Type:json)\', INDEX IDX_F07D0516C54C8C93 (type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE policy_filter_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, `schema` JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', UNIQUE INDEX name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE policy_layer (id INT AUTO_INCREMENT NOT NULL, policy_id INT NOT NULL, layer_id INT NOT NULL, INDEX IDX_5F1DA8E12D29E3C6 (policy_id), INDEX IDX_5F1DA8E1EA6EFDCD (layer_id), UNIQUE INDEX policy_id_layer_id (policy_id, layer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE policy_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, display_name VARCHAR(255) NOT NULL, `schema` JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', UNIQUE INDEX name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE policy_type_filter_type (id INT AUTO_INCREMENT NOT NULL, policy_type_id INT NOT NULL, policy_filter_type_id INT NOT NULL, INDEX IDX_5F4E9511A66034A7 (policy_type_id), INDEX IDX_5F4E9511F5CB7147 (policy_filter_type_id), UNIQUE INDEX policy_type_id_policy_filter_type_id (policy_type_id, policy_filter_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plan_policy ADD CONSTRAINT FK_44251D64E899029B FOREIGN KEY (plan_id) REFERENCES plan (plan_id)');
        $this->addSql('ALTER TABLE plan_policy ADD CONSTRAINT FK_44251D642D29E3C6 FOREIGN KEY (policy_id) REFERENCES policy (id)');
        $this->addSql('ALTER TABLE policy ADD CONSTRAINT FK_F07D0516C54C8C93 FOREIGN KEY (type_id) REFERENCES policy_type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE policy_layer ADD CONSTRAINT FK_5F1DA8E12D29E3C6 FOREIGN KEY (policy_id) REFERENCES policy (id)');
        $this->addSql('ALTER TABLE policy_layer ADD CONSTRAINT FK_5F1DA8E1EA6EFDCD FOREIGN KEY (layer_id) REFERENCES layer (layer_id)');
        $this->addSql('ALTER TABLE policy_type_filter_type ADD CONSTRAINT FK_5F4E9511A66034A7 FOREIGN KEY (policy_type_id) REFERENCES policy_type (id)');
        $this->addSql('ALTER TABLE policy_type_filter_type ADD CONSTRAINT FK_5F4E9511F5CB7147 FOREIGN KEY (policy_filter_type_id) REFERENCES policy_filter_type (id)');

        $this->addSql(
            <<< 'SQL'
    REPLACE INTO `policy_filter_type` (`id`, `name`, `schema`) VALUES
    (1,	'fleet',	'{\r\n  \"type\": \"object\",\r\n  \"properties\": {\r\n    \"fleets\": {\r\n      \"type\": \"integer\"\r\n    }\r\n  },\r\n  \"required\": [\"fleets\"]\r\n}'),
    (2,	'schedule',	'{\r\n  \"type\": \"object\",\r\n  \"properties\": {\r\n    \"months\": {\r\n      \"type\": \"array\",\r\n      \"items\": {\r\n        \"type\": \"integer\"\r\n      }\r\n    }\r\n  },\r\n  \"required\": [\"radius\"]\r\n}');    
    REPLACE INTO `policy_type` (`id`, `name`, `display_name`, `schema`) VALUES
    (1,	'buffer_zone',	'Buffer zone',	'{\r\n  \"type\": \"object\",\r\n  \"properties\": {\r\n    \"radius\": {\r\n      \"type\": \"number\"\r\n    }\r\n  },\r\n  \"required\": [\"radius\"]\r\n}'),
    (2,	'seasonal_closure',	'Seasonal closure',	NULL),
    (3,	'eco_gear',	'Ecological fishing gear',	'{\r\n  \"type\": \"object\",\r\n  \"properties\": {\r\n    \"per_country\": {\r\n      \"type\": \"object\",\r\n      \"additionalProperties\": false,\r\n      \"patternProperties\": {\r\n        \"^[0-9]+$\": {\r\n          \"type\": \"boolean\"\r\n        }\r\n      }\r\n    }\r\n  },\r\n  \"required\": [\"per_country\"]\r\n}\r\n');
    REPLACE INTO `policy_type_filter_type` (`id`, `policy_type_id`, `policy_filter_type_id`) VALUES
    (1,	1,	1),
    (2,	2,	1),
    (3,	2,	2),
    (4,	1,	2);
    SQL
        );
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
