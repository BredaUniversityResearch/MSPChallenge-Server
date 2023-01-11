<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230110104804 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Small update to ServerManager database';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    public function onUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
ALTER TABLE game_saves CHANGE save_timestamp save_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE users CHANGE token token LONGTEXT NOT NULL, 
    CHANGE refresh_token refresh_token LONGTEXT NOT NULL, 
    CHANGE refresh_token_expiration refresh_token_expiration DATETIME NOT NULL
SQL);
    }

    public function onDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
ALTER TABLE game_saves CHANGE save_timestamp save_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE users CHANGE token token TEXT NOT NULL, 
    CHANGE refresh_token refresh_token TEXT DEFAULT NULL, 
    CHANGE refresh_token_expiration refresh_token_expiration DATETIME DEFAULT NULL
SQL);
    }
}
