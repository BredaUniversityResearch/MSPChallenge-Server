<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20251210093841 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Added new columns transition_state, transition_month to game_list table. Changed game_state to enum type.';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `game_list`
        ADD `game_transition_month` int NULL AFTER `game_end_month`,
        CHANGE `game_current_month` `game_current_month` int NOT NULL DEFAULT '-1' AFTER `game_transition_month`,
        ADD `game_transition_state` enum('REQUEST','SETUP','PLAY','SIMULATION','FASTFORWARD','PAUSE','END') COLLATE 'utf8mb4_unicode_ci' NULL AFTER `session_state`,
        CHANGE `game_state` `game_state` enum('SETUP','PLAY','SIMULATION','FASTFORWARD','PAUSE','END') COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'SETUP' AFTER `game_transition_state`
        SQL);
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE `game_list`
        DROP `game_transition_month`,
        CHANGE `game_current_month` `game_current_month` int NOT NULL AFTER `game_end_month`,
        DROP `game_transition_state`,
        CHANGE `game_state` `game_state` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `session_state`
        SQL);
    }
}
