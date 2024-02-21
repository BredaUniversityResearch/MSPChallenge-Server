<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211222151102 extends MSPMigration
{
    private const COLUMNS_MISSING_DEFAULT = [
        'plan' => 'plan_description',
        'layer' => 'layer_name',
        'grid' => 'grid_distribution_only'
    ];

    public function getDescription(): string
    {
        return 'Add some missing default values, such that the database server\'s "strict mode" can be enabled, ' .
            'since the code is not handling it properly';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    /**
     * @throws SchemaException
     * @throws Exception
     */
    protected function onUp(Schema $schema): void
    {
        foreach (self::COLUMNS_MISSING_DEFAULT as $tableName => $columnName) {
            $column = $schema->getTable($tableName)->getColumn($columnName);
            $requiredDefaultValue = Type::lookupName($column->getType()) == Types::BOOLEAN ? 0 : '';
            if ($column->getDefault() === $requiredDefaultValue) {
                $this->write("Column {$columnName} for table {$tableName} already has required default value");
                continue;
            }
            $column->setDefault($requiredDefaultValue);
            $this->write("Added default value to column {$columnName} for table {$tableName}");
        }
    }

    protected function onDown(Schema $schema): void
    {
        foreach (self::COLUMNS_MISSING_DEFAULT as $tableName => $columnName) {
            $column = $schema->getTable($tableName)->getColumn($columnName);
            if ($column->getDefault() === null) {
                $this->write("Column {$columnName} for table {$tableName} already has no default value");
                continue;
            }
            $column->setDefault(null);
            $this->write("Removed default value from column {$columnName} for table {$tableName}");
        }
    }
}
