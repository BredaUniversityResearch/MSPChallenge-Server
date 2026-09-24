<?php

namespace App\Migration;

use App\Domain\Helper\Util;
use Closure;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use IntlException;

abstract class MSPMigration extends AbstractMigration
{
    private ?MSPDatabaseType $databaseType = null;

    /**
     * @throws Exception
     */
    private function resolveDatabaseName(): ?string
    {
        // DBAL 4 may provide an empty schema name for MySQL; prefer the active connection database name.
        $databaseName = $this->connection->getDatabase();
        if (is_string($databaseName) && $databaseName !== '') {
            return $databaseName;
        }

        $params = $this->connection->getParams();
        return $params['dbname'] ?? null;
    }

    public function preUp(Schema $schema): void
    {
        $this->databaseType = $this->getDatabaseType();
        $this->writeMigrationProgress('START UP');
    }

    public function preDown(Schema $schema): void
    {
        $this->databaseType = $this->getDatabaseType();
        $this->writeMigrationProgress('START DOWN');
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

    /**
     * @throws IntlException
     * @throws Exception
     */
    private function migrate(Schema $schema, Closure $migrationFunction): void
    {
        if (null == $this->databaseType) {
            // nothing to validate
            return;
        }

        $databaseName = $this->resolveDatabaseName();
        $this->abortIf(
            $databaseName === null,
            'Could not determine the active database name for migration validation. '
            .'Please check your connection configuration.'
        );

        switch ((string)$this->databaseType) {
            default:
                // nothing to validate
                return;
            case MSPDatabaseType::DATABASE_TYPE_GAME_SESSION:
                $this->skipIf(
                    !Util::hasPrefix($databaseName, $_ENV['DBNAME_SESSION_PREFIX'] ?? 'msp_session_'),
                    'This migrations requires a game session database. ' .
                    'Please use "--em" to set the game session entity manager. ' .
                    PHP_EOL . 'E.g. --em='.($_ENV['DBNAME_SESSION_PREFIX'] ?? 'msp_session_').'1'
                );
                break;
            case MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER:
                $this->skipIf(
                    !Util::hasPrefix($databaseName, $_ENV['DBNAME_SERVER_MANAGER'] ?? 'msp_server_manager'),
                    'This migrations requires a server manager database. Please use --em='.
                    ($_ENV['DBNAME_SERVER_MANAGER'] ?? 'msp_server_manager')
                );
                break;
        }

        // execute migration
        $migrationFunction();
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

    protected function addSql(
        string $sql,
        array $params = [],
        array $types = [],
    ): void {
        // It is generally safer to separate each SQL statement into individual addSql calls.
        //   This approach ensures better transactional integrity and more precise error reporting.
        $sqls = array_filter(explode(';', $sql));
        foreach ($sqls as $sql) {
            parent::addSql($sql, $params, $types);
        }
    }

    abstract protected function getDatabaseType(): MSPDatabaseType;
    abstract protected function onUp(Schema $schema): void;
    abstract protected function onDown(Schema $schema): void;

    /**
     * @throws Exception
     */
    private function writeMigrationProgress(string $state): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        $argv = $_SERVER['argv'] ?? [];
        if (!is_array($argv) || !in_array('doctrine:migrations:migrate', $argv, true)) {
            return;
        }

        $databaseName = $this->resolveDatabaseName() ?? '<unknown-db>';
        fwrite(
            STDERR,
            sprintf('[MIGRATION %s] %s on %s%s', $state, static::class, $databaseName, PHP_EOL)
        );
    }
}
