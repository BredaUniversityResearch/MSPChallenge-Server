<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20230830153300 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Adds api_refresh_token table';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
CREATE TABLE api_refresh_token (
    id INT AUTO_INCREMENT NOT NULL, refresh_token LONGTEXT NOT NULL, user_id int(11) NOT NULL, 
    valid DATETIME NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), 
    PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` 
    ENGINE = InnoDB
SQL);
    }

    public function onDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE api_refresh_token');
    }
}
