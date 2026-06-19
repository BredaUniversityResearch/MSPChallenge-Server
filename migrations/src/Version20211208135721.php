<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
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

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    /**
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function onUp(Schema $schema): void
    {
        $this->addColumn($schema->getTable('layer'), 'layer_entity_value_max', Types::FLOAT, [
            'Notnull' => false
        ]);
    }

    protected function onDown(Schema $schema): void
    {
        $this->dropColumn($schema->getTable('layer'), 'layer_entity_value_max');
    }
}
