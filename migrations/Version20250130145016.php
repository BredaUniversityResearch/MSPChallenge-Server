<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250130145016 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Added kpi type "EXTERNAL"';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `kpi`
        CHANGE `kpi_type` `kpi_type` enum('ECOLOGY','ENERGY','SHIPPING','EXTERNAL') COLLATE 'utf8mb4_general_ci' NULL AFTER `kpi_month`
        SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `kpi`
        CHANGE `kpi_type` `kpi_type` enum('ECOLOGY','ENERGY','SHIPPING') COLLATE 'utf8mb4_general_ci' NULL AFTER `kpi_month`
        SQL
        );
    }
}
