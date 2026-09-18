<?php

declare(strict_types=1);

namespace DoctrineMigrations\ServerManager;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260910160623 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add terms_versions and terms_acceptances tables to support a versioned Terms of Service gate.';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    /**
     * @throws Exception
     */
    protected function onUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
          CREATE TABLE `terms_versions` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `version` VARCHAR(50) NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `acknowledgment_heading_pattern` VARCHAR(150) NOT NULL DEFAULT '/^\\d+\\.\\s*Acknowledgment$/i',
            `created_at` DATETIME NOT NULL,
            `current` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(`id`)
          ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
          CREATE TABLE `terms_acceptances` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `user_id` INT NOT NULL,
            `terms_version_id` INT NOT NULL,
            `accepted_at` DATETIME NOT NULL,
            UNIQUE INDEX `user_terms_unique` (`user_id`, `terms_version_id`),
            INDEX `IDX_terms_acceptances_terms_version_id` (`terms_version_id`),
            PRIMARY KEY(`id`)
          ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
          ALTER TABLE `terms_acceptances`
            ADD CONSTRAINT `FK_terms_acceptances_user_id`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        SQL);

        $this->addSql(<<<'SQL'
          ALTER TABLE `terms_acceptances`
            ADD CONSTRAINT `FK_terms_acceptances_terms_version_id`
            FOREIGN KEY (`terms_version_id`) REFERENCES `terms_versions` (`id`) ON DELETE CASCADE
        SQL);

        $this->addSql(<<<'SQL'
          INSERT INTO `terms_versions` (`version`, `file_path`, `created_at`, `current`) VALUES
          ('5.0', 'docs/TOS/B_Terms_and_Conditions.md', NOW(), 1)
        SQL);
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE `terms_acceptances`');
        $this->addSql('DROP TABLE `terms_versions`');
    }
}
