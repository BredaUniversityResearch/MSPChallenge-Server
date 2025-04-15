<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250410181450 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Create immersive_session, immersive_session_connection and immersive_session_region tables';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        CREATE TABLE immersive_session (
            id INT AUTO_INCREMENT NOT NULL,
            region_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            `type` enum('mixed-reality') NOT NULL DEFAULT 'mixed-reality',
            MONTH INT NOT NULL,
            DATA JSON DEFAULT NULL COMMENT '(DC2Type:json_document)',
            INDEX immersive_session_region_id (region_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL
        );
        $this->addSql(<<<'SQL'
        CREATE TABLE immersive_session_connection (
            id INT AUTO_INCREMENT NOT NULL,
            session_id INT NOT NULL,
            docker_api_id INT NOT NULL,
            port INT NOT NULL,
            docker_container_id VARCHAR(255) NOT NULL,
            INDEX immersive_session_connection_session_id (session_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
        SQL
        );
        $this->addSql('CREATE TABLE immersive_session_region (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, bottom_left_x DOUBLE PRECISION NOT NULL, bottom_left_y DOUBLE PRECISION NOT NULL, top_right_x DOUBLE PRECISION NOT NULL, top_right_y DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE immersive_session ADD CONSTRAINT immersive_session_region_id FOREIGN KEY (region_id) REFERENCES immersive_session_region (id)');
        $this->addSql('ALTER TABLE immersive_session_connection ADD CONSTRAINT immersive_session_connection_session_id FOREIGN KEY (session_id) REFERENCES immersive_session (id)');

    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE immersive_session DROP FOREIGN KEY immersive_session_region_id');
        $this->addSql('ALTER TABLE immersive_session_connection DROP FOREIGN KEY immersive_session_connection_session_id');
        $this->addSql('DROP TABLE immersive_session');
        $this->addSql('DROP TABLE immersive_session_connection');
        $this->addSql('DROP TABLE immersive_session_region');
    }
}
