<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250410181235 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Create immersive_session_docker_api and immersive_session_type tables';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql('CREATE TABLE immersive_session_docker_api (id INT AUTO_INCREMENT NOT NULL, address VARCHAR(255) NOT NULL, port INT NOT NULL, scheme VARCHAR(255) NOT NULL, available TINYINT(1) NOT NULL, last_ping DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE immersive_session_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, data_schema JSON DEFAULT NULL COMMENT \'(DC2Type:json_document)\', data_default JSON DEFAULT NULL COMMENT \'(DC2Type:json_document)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE immersive_session_docker_api');
        $this->addSql('DROP TABLE immersive_session_type');
    }
}
