<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20230717130110 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Simplifies Server Manager';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE game_list CHANGE password_player password_player LONGTEXT DEFAULT NULL, CHANGE api_access_token api_access_token VARCHAR(32) DEFAULT NULL, CHANGE server_version server_version VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE game_saves CHANGE save_timestamp save_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE game_list CHANGE password_player password_player LONGTEXT NOT NULL, CHANGE api_access_token api_access_token VARCHAR(32) NOT NULL, CHANGE server_version server_version VARCHAR(45) NOT NULL');
        $this->addSql('ALTER TABLE game_saves CHANGE save_timestamp save_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
