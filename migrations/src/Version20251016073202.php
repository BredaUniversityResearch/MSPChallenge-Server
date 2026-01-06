<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20251016073202 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Generate unique name_unique_when_original_id_null column for layer table';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }


    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE layer
        ADD COLUMN name_unique_when_original_id_null VARCHAR(255) GENERATED ALWAYS AS (IF(layer_original_id IS NULL, layer_name, NULL)) STORED,
        ADD UNIQUE INDEX name_unique_when_original_id_null (name_unique_when_original_id_null);
        SQL
        );
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE layer DROP INDEX name_unique_when_original_id_null');
        $this->addSql('ALTER TABLE layer DROP COLUMN name_unique_when_original_id_null');
    }
}
