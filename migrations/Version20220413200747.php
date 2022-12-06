<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Types;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220413200747 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Alter table api_batch';
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
        $sql = <<< 'SQL'
ALTER TABLE `api_batch`
CHANGE `api_batch_state` `api_batch_state`
enum('Setup','Queued','Executing','Success','Failed') NOT NULL DEFAULT 'Setup'
SQL;
        $this->addSql($sql);

        $table = $schema->getTable('api_batch');
        $column = $this->addIndexedColumn($table, 'api_batch_country_id', Types::INTEGER);
        $table->hasForeignKey('api_batch_ibfk_1') or
            $table->addForeignKeyConstraint(
                'country',
                [$column->getName()],
                ['country_id'],
                ['onDelete' => 'no action', 'onUpdate' => 'no action'],
                'api_batch_ibfk_1'
            );

        $column = $this->addIndexedColumn($table, 'api_batch_user_id', Types::INTEGER);
        $table->hasForeignKey('api_batch_ibfk_2') or
            $table->addForeignKeyConstraint(
                'user',
                [$column->getName()],
                ['user_id'],
                ['onDelete' => 'no action', 'onUpdate' => 'no action'],
                'api_batch_ibfk_2'
            );
    }

    /**
     * @throws SchemaException
     */
    protected function onDown(Schema $schema): void
    {
        $sql = <<< 'SQL'
ALTER TABLE `api_batch`
CHANGE `api_batch_state` `api_batch_state`
enum('Setup','Success','Failed') NOT NULL DEFAULT 'Setup'
SQL;
        $this->addSql($sql);

        $table = $schema->getTable('api_batch');
        !$table->hasForeignKey('api_batch_ibfk_1') or $table->removeForeignKey('api_batch_ibfk_1');
        $this->dropIndexedColumn($table, 'api_batch_country_id');

        !$table->hasForeignKey('api_batch_ibfk_2') or $table->removeForeignKey('api_batch_ibfk_2');
        $this->dropIndexedColumn($table, 'api_batch_user_id');
    }
}
