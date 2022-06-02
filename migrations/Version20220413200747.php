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
        if (!$table->hasColumn('api_batch_country_id')) {
            $column = $table->addColumn('api_batch_country_id', Types::INTEGER);
            $this->write("Added column {$column->getName()} for table {$table->getName()}");
        } else {
            $column = $table->getColumn('api_batch_country_id');
            $this->write("Column api_batch_country_id for table {$table->getName()} already exists");
        }

        $numIndexes = count($table->getIndexes()) + count($table->getForeignKeys());
        // add missing indexes if any
        $table->hasIndex($column->getName()) or
            $table->addIndex([$column->getName()], $column->getName());
        $table->hasForeignKey('api_batch_ibfk_1') or
            $table->addForeignKeyConstraint(
                'country',
                [$column->getName()],
                ['country_id'],
                ['onDelete' => 'no action', 'onUpdate' => 'no action'],
                'api_batch_ibfk_1'
            );

        if (!$table->hasColumn('api_batch_user_id')) {
            $column = $table->addColumn('api_batch_user_id', Types::INTEGER);
            $this->write("Added column {$column->getName()} for table {$table->getName()}");
        } else {
            $column = $table->getColumn('api_batch_user_id');
            $this->write("Column api_batch_user_id for table {$table->getName()} already exists");
        }

        // add missing indexes if any
        $table->hasIndex($column->getName()) or $table->addIndex([$column->getName()], $column->getName());
        $table->hasForeignKey('api_batch_ibfk_2') or
            $table->addForeignKeyConstraint(
                'user',
                [$column->getName()],
                ['user_id'],
                ['onDelete' => 'no action', 'onUpdate' => 'no action'],
                'api_batch_ibfk_2'
            );

        $addedIndexes = count($table->getIndexes()) + count($table->getForeignKeys()) - $numIndexes;
        if ($addedIndexes > 0) {
            $this->write("Added {$addedIndexes} missing indexes.");
        }
    }

    protected function onDown(Schema $schema): void
    {
        $sql = <<< 'SQL'
ALTER TABLE `api_batch`
CHANGE `api_batch_state` `api_batch_state`
enum('Setup','Success','Failed') NOT NULL DEFAULT 'Setup'
SQL;
        $this->addSql($sql);

        $table = $schema->getTable('api_batch');
        $numIndexes = count($table->getIndexes()) + count($table->getForeignKeys());
        $numColumns = count($table->getColumns());
        !$table->hasIndex('api_batch_country_id') or $table->dropIndex('api_batch_country_id');
        !$table->hasForeignKey('api_batch_ibfk_1') or $table->removeForeignKey('api_batch_ibfk_1');
        !$table->hasColumn('api_batch_country_id') or $table->dropColumn('api_batch_country_id');

        !$table->hasIndex('api_batch_user_id') or $table->dropIndex('api_batch_user_id');
        !$table->hasForeignKey('api_batch_ibfk_2') or $table->removeForeignKey('api_batch_ibfk_2');
        !$table->hasColumn('api_batch_user_id') or $table->dropColumn('api_batch_user_id');

        $droppedIndexes = $numIndexes - count($table->getIndexes()) + count($table->getForeignKeys());
        if ($droppedIndexes > 0) {
            $this->write("Dropped {$droppedIndexes} indexes.");
        }
        $droppedColumns = $numColumns - count($table->getColumns());
        if ($droppedColumns > 0) {
            $this->write("Dropped {$droppedColumns} columns.");
        }
    }
}
