<?php

declare(strict_types=1);

namespace DoctrineMigrations\GameSession;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260917123150 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add index on kpi_type, kpi_name, and kpi_month columns in kpi table';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE kpi ADD INDEX index_kpi_type_name_month (kpi_type, kpi_name, kpi_month);
        SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE kpi DROP INDEX index_kpi_type_name_month;
        SQL
        );
    }
}
