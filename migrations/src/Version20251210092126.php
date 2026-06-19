<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;
final class Version20251210092126 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Fixed nullable fields and added new fields: transition_state, transition_month in game table';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        ALTER TABLE `game`
        CHANGE `game_start` `game_start` int NOT NULL DEFAULT '2010' COMMENT 'starting year' AFTER `game_id`,
        CHANGE `game_state` `game_state` enum('SETUP','PLAY','SIMULATION','FASTFORWARD','PAUSE','END') COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT 'SETUP' AFTER `game_start`,
        ADD `game_transition_state` enum('REQUEST','SETUP','PLAY','SIMULATION','FASTFORWARD','PAUSE','END') COLLATE 'utf8mb4_general_ci' NULL AFTER `game_state`,
        CHANGE `game_lastupdate` `game_lastupdate` double NOT NULL DEFAULT '0' AFTER `game_transition_state`,
        CHANGE `game_currentmonth` `game_currentmonth` int NOT NULL DEFAULT '-1' AFTER `game_lastupdate`,
        ADD `game_transition_month` int NULL AFTER `game_currentmonth`,
        CHANGE `game_energyupdate` `game_energyupdate` tinyint NOT NULL DEFAULT '0' AFTER `game_transition_month`,
        CHANGE `game_planning_gametime` `game_planning_gametime` int NOT NULL DEFAULT '36' COMMENT 'how many in-game months the planning phase takes' AFTER `game_energyupdate`,
        CHANGE `game_planning_realtime` `game_planning_realtime` int NOT NULL DEFAULT '1' COMMENT 'how long the planning era takes' AFTER `game_planning_gametime`,
        CHANGE `game_planning_era_realtime` `game_planning_era_realtime` varchar(256) COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT '0' COMMENT 'thie game_planning_realtime for all eras. A comma separated list' AFTER `game_planning_realtime`,
        CHANGE `game_planning_monthsdone` `game_planning_monthsdone` int NOT NULL DEFAULT '0' COMMENT 'amount of months done in this part of the era (planning or simulation)' AFTER `game_planning_era_realtime`,
        CHANGE `game_eratime` `game_eratime` int NOT NULL DEFAULT '120' COMMENT 'how long the entire era takes (default: 10 years)' AFTER `game_planning_monthsdone`,
        CHANGE `game_configfile` `game_configfile` varchar(128) COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `game_eratime`,
        CHANGE `game_autosave_month_interval` `game_autosave_month_interval` int NOT NULL DEFAULT '120' AFTER `game_configfile`
        SQL);
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE `game`
        CHANGE `game_start` `game_start` int NULL DEFAULT '2010' COMMENT 'starting year' AFTER `game_id`,
        CHANGE `game_state` `game_state` enum('SETUP','PLAY','SIMULATION','FASTFORWARD','PAUSE','END') COLLATE 'utf8mb4_general_ci' NULL DEFAULT 'SETUP' AFTER `game_start`,
        DROP `game_transition_state`,
        CHANGE `game_lastupdate` `game_lastupdate` double NULL DEFAULT '0' AFTER `game_state`,
        CHANGE `game_currentmonth` `game_currentmonth` int NULL DEFAULT '-1' AFTER `game_lastupdate`,
        DROP `game_transition_month`,
        CHANGE `game_energyupdate` `game_energyupdate` tinyint NULL DEFAULT '0' AFTER `game_currentmonth`,
        CHANGE `game_planning_gametime` `game_planning_gametime` int NULL DEFAULT '36' COMMENT 'how many in-game months the planning phase takes' AFTER `game_energyupdate`,
        CHANGE `game_planning_realtime` `game_planning_realtime` int NULL DEFAULT '1' COMMENT 'how long the planning era takes' AFTER `game_planning_gametime`,
        CHANGE `game_planning_era_realtime` `game_planning_era_realtime` varchar(256) COLLATE 'utf8mb4_general_ci' NULL DEFAULT '0' COMMENT 'thie game_planning_realtime for all eras. A comma separated list' AFTER `game_planning_realtime`,
        CHANGE `game_planning_monthsdone` `game_planning_monthsdone` int NULL DEFAULT '0' COMMENT 'amount of months done in this part of the era (planning or simulation)' AFTER `game_planning_era_realtime`,
        CHANGE `game_eratime` `game_eratime` int NULL DEFAULT '120' COMMENT 'how long the entire era takes (default: 10 years)' AFTER `game_planning_monthsdone`,
        CHANGE `game_configfile` `game_configfile` varchar(128) COLLATE 'utf8mb4_general_ci' NULL AFTER `game_eratime`,
        CHANGE `game_autosave_month_interval` `game_autosave_month_interval` int NULL DEFAULT '120' AFTER `game_configfile`
        SQL);
    }
}
