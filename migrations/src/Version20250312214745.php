<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20250312214745 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Added policy type "sand_extraction"';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql("ALTER TABLE `policy` MODIFY `type` enum('energy','fishing','shipping','buffer_zone','seasonal_closure','eco_gear','sand_extraction') NOT NULL DEFAULT 'seasonal_closure'");
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql("ALTER TABLE `policy` MODIFY `type` enum('energy','fishing','shipping','buffer_zone','seasonal_closure','eco_gear') NOT NULL DEFAULT 'seasonal_closure'");
    }
}
