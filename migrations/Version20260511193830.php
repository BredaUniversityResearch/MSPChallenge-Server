<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511193830 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Added enum value multitypesourcepolygon to column layer_editing_type in layer table';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `layer`
        CHANGE `layer_editing_type` `layer_editing_type` enum('cable', 'transformer', 'socket', 'sourcepoint', 'sourcepolygon', 'sourcepolygonpoint', 'multitype', 'protection', 'multitypesourcepolygon') NULL
        SQL);
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE `layer`
        CHANGE `layer_editing_type` `layer_editing_type` enum('cable', 'transformer', 'socket', 'sourcepoint', 'sourcepolygon', 'sourcepolygonpoint', 'multitype', 'protection') NULL
        SQL);
    }
}
