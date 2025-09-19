<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Interface\WatchdogInterface;
use App\Entity\SessionAPI\Watchdog;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250116085814 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Extend game_watchdog_servers table with: server_id, port, scheme, simulation_settings';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE `game_watchdog_servers`
        ADD `server_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)' AFTER `id`,
        ADD `port` int unsigned NOT NULL DEFAULT '80' AFTER `address`,
        ADD `scheme` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'http' AFTER `port`,
        ADD `simulation_settings` longtext COLLATE 'utf8mb4_unicode_ci' NULL COMMENT '(DC2Type:json_document)' AFTER `scheme`,
        DROP INDEX `UNIQ_C35754DF5E237E06`,
        DROP INDEX `UNIQ_C35754DFD4E6F81`,
        ADD UNIQUE `uq_server_id` (`server_id`),
        ADD UNIQUE `uq_scheme_address_port` (`scheme`, `address`, `port`)
        SQL
        );
        $watchdogServerId = Watchdog::getInternalServerId()->toBinary();
        $watchdogAddress = $_ENV['WATCHDOG_ADDRESS'] ?? WatchdogInterface::DEFAULT_ADDRESS;
        $watchdogPort = (int)($_ENV['WATCHDOG_PORT'] ?? 45000);
        $watchdogScheme = str_replace('://', '', $_ENV['WATCHDOG_SCHEME'] ?? 'http');
        $this->addSql(<<<"SQL"
        UPDATE `game_watchdog_servers` SET
        `server_id` = '{$watchdogServerId}',
        `name` = 'Default: the same server machine',
        `address` = '{$watchdogAddress}',
        `port` = '{$watchdogPort}',
        `scheme` = '{$watchdogScheme}',
        `available` = '1'
        WHERE `id` = '1'
        SQL);
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        ALTER TABLE `game_watchdog_servers`
        DROP INDEX `uq_scheme_address_port`,
        DROP `server_id`,
        DROP `port`,
        DROP `scheme`,
        DROP `simulation_settings`,
        ADD UNIQUE `UNIQ_C35754DF5E237E06` (`name`),
        ADD UNIQUE `UNIQ_C35754DFD4E6F81` (`address`)
        SQL
        );
        $this->addSql(<<<'SQL'
        UPDATE `game_watchdog_servers` SET
        `name` = 'Default: the same server machine',
        `address` = 'localhost'
        WHERE `id` = '1'
        SQL);
    }
}
