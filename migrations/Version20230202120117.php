<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230202120117 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add unique key to plan_layers\' plan_layer_plan_id, plan_layer_layer_id column pair';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `plan_layer` ADD UNIQUE `plan_layer_plan_id_plan_layer_layer_id` '.
            '(`plan_layer_plan_id`, `plan_layer_layer_id`)'
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `plan_layer` DROP INDEX `plan_layer_plan_id_plan_layer_layer_id`');
    }
}
