<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;
final class Version20251016195625 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add column kpi_type_external to kpi table';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE `kpi`
        ADD `kpi_type_external` varchar(191) NULL DEFAULT NULL AFTER `kpi_type`,
        ADD INDEX `kpi_type_external` (`kpi_type_external`),
        ADD INDEX `kpi_type_kpi_type_external` (`kpi_type`, `kpi_type_external`)
        SQL);
        $this->addSql("UPDATE `kpi` SET `kpi_type_external` = 'external' WHERE `kpi_type` = 'EXTERNAL'");
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE `kpi` DROP INDEX `kpi_type_external`, DROP INDEX `kpi_type_kpi_type_external`, DROP `kpi_type_external`');
    }
}
