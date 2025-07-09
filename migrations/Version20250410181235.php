<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250410181235 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Create docker_api and immersive_session_type tables';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql(<<< 'SQL'
        CREATE TABLE `docker_api` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `address` varchar(255) NOT NULL,
          `port` int unsigned NOT NULL,
          `scheme` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci       
        SQL
        );
        $this->addSql(<<< 'SQL'
            CREATE TABLE immersive_session_type (
            id INT unsigned AUTO_INCREMENT NOT NULL, 
            `type` enum('ar','mr','vr','xr') NOT NULL DEFAULT 'mr', 
            name VARCHAR(255) NOT NULL, 
            data_schema JSON DEFAULT NULL COMMENT '(DC2Type:json_document)',
            data_default JSON DEFAULT NULL COMMENT '(DC2Type:json_document)',
            UNIQUE INDEX immersive_session_type_type (type), 
            PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE docker_api');
        $this->addSql('DROP TABLE immersive_session_type');
    }
}
