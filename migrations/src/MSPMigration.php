<?php

namespace DoctrineMigrations;

use App\Domain\Helper\Util;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

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

    public function up(Schema $schema): void
    {
        $this->validateSchema($schema);
        $this->onUp($schema);
    }

    public function down(Schema $schema): void
    {
        $this->validateSchema($schema);
        $this->onDown($schema);
    }

    abstract protected function getDatabaseType(): ?MSPDatabaseType;
    abstract protected function onUp(Schema $schema): void;
    abstract protected function onDown(Schema $schema): void;
}
