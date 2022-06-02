<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211208135721 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add layer_entity_value_max column to layer table';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $table = $schema->getTable('layer');
        if ($table->hasColumn('layer_entity_value_max')) {
            $this->write('Column layer_entity_value_max for table layer already exists');
            return;
        }
        $table->addColumn('layer_entity_value_max', Types::FLOAT, [
            'Notnull' => false
        ]);
        $this->write('Added column layer_entity_value_max to table layer');
    }

    protected function onDown(Schema $schema): void
    {
        $table = $schema->getTable('layer');
        if (!$table->hasColumn('layer_entity_value_max')) {
            $this->write('Column layer_entity_value_max for table layer already gone');
            return;
        }
        $table->dropColumn('layer_entity_value_max');
        $this->write('Dropped column layer_entity_value_max from table layer');
    }
}
