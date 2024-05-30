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
            CREATE TABLE `plan_policy` (`id` INT AUTO_INCREMENT NOT NULL, `plan_id` INT NOT NULL, `policy_id` INT NOT NULL, INDEX `idx_plan_policy_plan_id` (`plan_id`), INDEX `idx_plan_policy_policy_id` (`policy_id`), UNIQUE INDEX `plan_id_policy_id` (`plan_id`, `policy_id`), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            CREATE TABLE `policy` (`id` INT AUTO_INCREMENT NOT NULL, `type` enum('buffer_zone','seasonal_closure','eco_gear') NOT NULL DEFAULT 'seasonal_closure', `data` JSON DEFAULT NULL COMMENT '(DC2Type:json)', INDEX `idx_policy_type` (`type`), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            ALTER TABLE `plan_policy` ADD CONSTRAINT `fk_plan_policy_plan_id` FOREIGN KEY (`plan_id`) REFERENCES `plan` (`plan_id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `plan_policy` ADD CONSTRAINT `fk_plan_policy_policy_id` FOREIGN KEY (`policy_id`) REFERENCES `policy` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `plan_layer` CHANGE `plan_layer_state` `plan_layer_state` enum('ACTIVE','ASSEMBLY','WAIT') COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT 'WAIT' AFTER `plan_layer_layer_id`;
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
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('DROP TABLE plan_policy');
        $this->addSql('DROP TABLE policy');
    }
}
