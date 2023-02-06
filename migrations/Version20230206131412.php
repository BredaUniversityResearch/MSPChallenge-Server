<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230206131412 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'drop users account_owner column, add unique key on users username column';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql('DROP INDEX username ON users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_1483a5e9f85e0677 ON users');
        $this->addSql('CREATE UNIQUE INDEX username ON users (username)');
    }
}
