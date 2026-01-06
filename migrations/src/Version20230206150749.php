<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20230206150749 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'drop users account_owner column, add unique key on users username column';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP account_owner');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
    }

    protected function onDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_1483A5E9F85E0677 ON users');
        $this->addSql('ALTER TABLE users ADD account_owner SMALLINT DEFAULT 0 NOT NULL');
    }
}
