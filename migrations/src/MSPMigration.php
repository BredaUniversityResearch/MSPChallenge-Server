<?php

namespace DoctrineMigrations;

use App\Domain\Helper\Util;
use Closure;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use http\Message;
use IntlException;
use MessageFormatter;
use function msgfmt_create;

abstract class MSPMigration extends AbstractMigration
{
    private ?MSPDatabaseType $databaseType = null;

    private function validateSchema(Schema $schema): void
    {
        if (null == $this->databaseType) {
            // nothing to validate
            return;
        }
        switch ((string)$this->databaseType) {
            default:
                // nothing to validate
                return;
            case MSPDatabaseType::DATABASE_TYPE_GAME_SESSION:
                $this->abortIf(
                    !Util::hasPrefix($schema->getName(), $_ENV['DBNAME_SESSION_PREFIX'] ?? 'msp_session_'),
                    'This is no game session connection. Please use "--conn" to set the game session connection. ' .
                    PHP_EOL . 'E.g. --conn=msp_session_1'
                );
                break;
            case MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER:
                $this->abortIf(
                    $schema->getName() != $_ENV['DBNAME_SERVER_MANAGER'],
                    'This is no server manager connection. Please use --conn=' . $schema->getName()
                );
                break;
        }
    }

    public function preUp(Schema $schema): void
    {
        $this->databaseType = $this->getDatabaseType();
    }

    public function preDown(Schema $schema): void
    {
        $this->databaseType = $this->getDatabaseType();
    }

    protected function countIndexes(Schema $schema): int
    {
        $numIndexes = 0;
        $tables = $schema->getTables();
        foreach ($tables as $table) {
            $numIndexes += count($table->getIndexes()) + count($table->getForeignKeys());
        }
        return $numIndexes;
    }

    protected function countColumns(Schema $schema): int
    {
        $numColumns = 0;
        $tables = $schema->getTables();
        foreach ($tables as $table) {
            $numColumns += count($table->getColumns());
        }
        return $numColumns;
    }

    public function up(Schema $schema): void
    {
        $this->migrate($schema, function () use ($schema) {
            $this->onUp($schema);
        });
    }

    public function down(Schema $schema): void
    {
        $this->migrate($schema, function () use ($schema) {
            $this->onDown($schema);
        });
    }

    private function migrate(Schema $schema, Closure $migrationFunction)
    {
        $this->validateSchema($schema);

        // collect data to detect changes after migration
        $numTables = count($schema->getTables());
        $numColumns = $this->countColumns($schema);
        $numIndexes = $this->countIndexes($schema);

        // execute migration
        $migrationFunction();

        // detect and output changes automatically
        $this->writeDifferences(
            count($schema->getTables()) - $numTables,
            $this->countColumns($schema) - $numColumns,
            $this->countIndexes($schema) - $numIndexes
        );
    }

    /**
     * @throws IntlException
     */
    private function writeDifferences(int $numTablesDiff, int $numColumnsDiff, int $numIndexesDiff)
    {
        // see https://www.php.net/manual/en/messageformatter.formatmessage.php#112661
        if ($numTablesDiff != 0) {
            $this->write(
                $this->createMessageFormatter(
                    'en_US',
                    '{0, choice, ' . PHP_INT_MIN . ' #Dropped| 0 #Added} {1, plural, =1{# table} other{# tables}}'
                )
                ->format([$numTablesDiff, abs($numTablesDiff)])
            );
        }
        if ($numColumnsDiff != 0) {
            $this->write(
                $this->createMessageFormatter(
                    'en_US',
                    '{0, choice, ' . PHP_INT_MIN . ' #Dropped| 0 #Added} {1, plural, =1{# column} other{# columns}}'
                )
                ->format([$numColumnsDiff, abs($numColumnsDiff)])
            );
        }
        if ($numIndexesDiff != 0) {
            $this->write(
                $this->createMessageFormatter(
                    'en_US',
                    '{0, choice, ' . PHP_INT_MIN . ' #Dropped| 0 #Added} {1, plural, =1{# index} other{# indexes}}'
                )
                ->format([$numIndexesDiff, abs($numIndexesDiff)])
            );
        }
    }

    /**
     * @throws IntlException
     */
    private function createMessageFormatter($locale, $pattern): MessageFormatter
    {
        return new MessageFormatter($locale, $pattern);
    }

    /**
     * @throws SchemaException
     */
    protected function addColumn(Table $table, string $columnName, string $typeName, array $options = []): Column
    {
        if (!$table->hasColumn($columnName)) {
            $column = $table->addColumn($columnName, $typeName, $options);
            $this->write("Added column {$columnName} for table {$table->getName()}");
        } else {
            $column = $table->getColumn($columnName);
            $this->write("Column {$columnName} for table {$table->getName()} already exists");
        }
        return $column;
    }

    /**
     * @throws SchemaException
     */
    protected function addIndexedColumn(Table $table, string $columnName, string $typeName, array $options = []): Column
    {
        $column = $this->addColumn($table, $columnName, $typeName, $options);
        $table->hasIndex($columnName) or $table->addIndex([$columnName], $columnName);
        return $column;
    }

    protected function dropColumn(Table $table, string $columnName): void
    {
        if (!$table->hasColumn($columnName)) {
            $this->write("Column {$columnName} for table layer already gone");
            return;
        }
        $table->dropColumn($columnName);
        $this->write("Dropped column {$columnName} from table layer");
    }

    /**
     * @throws SchemaException
     */
    protected function dropIndexedColumn(Table $table, string $columnName): void
    {
        !$table->hasIndex($columnName) or $table->dropIndex($columnName);
        $this->dropColumn($table, $columnName);
    }

    abstract protected function getDatabaseType(): ?MSPDatabaseType;
    abstract protected function onUp(Schema $schema): void;
    abstract protected function onDown(Schema $schema): void;
}
