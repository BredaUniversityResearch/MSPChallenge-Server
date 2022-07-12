<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Types;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220124120237 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Alter geometry_mspid column from type int to string';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $table = $schema->getTable('geometry');
        $column = $table->getColumn('geometry_mspid');
        if ($column->getType()->getName() == Types::STRING) {
            $this->write("Column {$column->getName()} for table {$table->getName()} already altered to string");
            return;
        }
        $column->setType(new StringType());
        $column->setLength(16);
        $this->write("Altered column {$column->getName()} for table {$table->getName()} to type string");
    }

    protected function onDown(Schema $schema): void
    {
        $table = $schema->getTable('geometry');
        $column = $table->getColumn('geometry_mspid');
        if ($column->getType()->getName() == Types::INTEGER) {
            $this->write("Column {$column->getName()} for table {$table->getName()} already altered to int");
            return;
        }
        $column->setType(new IntegerType());
        $this->write("Altered column {$column->getName()} for table {$table->getName()} to type int");
    }
}
