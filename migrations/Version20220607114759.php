<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Types;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220607114759 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add column api_batch_server_id, api_batch_communicated to api_batch';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    /**
     * @throws SchemaException
     */
    protected function onUp(Schema $schema): void
    {
        $table = $schema->getTable('api_batch');
        $this->addIndexedColumn($table, 'api_batch_server_id', Types::STRING);
        $this->addColumn($table, 'api_batch_communicated', Types::BOOLEAN)->setDefault(false);
    }

    /**
     * @throws SchemaException
     */
    protected function onDown(Schema $schema): void
    {
        $table = $schema->getTable('api_batch');
        $this->dropIndexedColumn($table, 'api_batch_server_id');
        $this->dropColumn($table, 'api_batch_communicated');
    }
}
