<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\API\v1\Game;
use App\Domain\API\v1\Simulation;
use App\Domain\Common\InternalSimulationName;
use App\Domain\Services\SimulationHelper;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\SessionAPI\Watchdog;
use Doctrine\DBAL\Schema\Schema;
use Exception;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

// todo : see https://github.com/doctrine/DoctrineMigrationsBundle/issues/521
// phpcs:ignore Generic.Files.LineLength.TooLong
// @phpstan-ignore-next-line " Class DoctrineMigrations\Version20241128170458 implements deprecated interface Symfony\Component\DependencyInjection\ContainerAwareInterface: since Symfony 6.4, use dependency injection instead"
final class Version20241128170458 extends MSPMigration implements ContainerAwareInterface
{
    private ?ContainerInterface $container = null;

    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    public function getDescription(): string
    {
        return 'Add simulation table if it does not exists. Remove Game columns related to simulation if they exists';
    }

    /**
     * @return void
     * @throws Exception
     */
    public function insertSimulationRecords(): void
    {
        $result = preg_match('/msp_session_(\d+)/', $this->connection->getDatabase(), $matches);
        $this->abortIf(
            $result !== 1,
            'Database name does not match the expected format: msp_session_{id}'
        );
        $sessionId = (int)$matches[1];
        $game = new Game();
        $game->setGameSessionId($sessionId);
        try {
            $config = $game->GetGameConfigValues();
        } catch (Exception $e) {
            $this->warnIf(
                true,
                'Failed to retrieve game configuration values. Skipping simulation registration, error: '.
                $e->getMessage()
            );
            return; // nothing to do
        }

        // filter possible internal simulations with the ones present in the config
        $simulations = array_intersect_key(array_flip(array_map(
            fn(InternalSimulationName $e) => $e->value,
            InternalSimulationName::cases()
        )), $config);
        if (empty($simulations)) {
            $this->warnIf(true, 'No simulations found to register in game configuration');
            return; // no configured simulations
        }
        $sim = new Simulation();
        $sim->setGameSessionId($sessionId);
        /** @var SimulationHelper $simulationHelper */
        $simulationHelper = $this->container->get(SimulationHelper::class);
        $versions = $simulationHelper->getConfiguredSimulationTypes($sessionId);
        foreach ($versions as $name => $version) {
            $nameLowered = strtolower($name);
            $sql = <<<"SQL"
                INSERT INTO simulation (watchdog_id, name, version, last_month)
                SELECT
                    '1' as `watchdog_id`,
                    '{$name}' as `name`,
                    '{$version}' as `version`,
                    game_{$nameLowered}_lastmonth as last_month
                FROM game
                SQL;
            $this->addSql($sql);
        }
        // in-case there is no game record for the simulation, insert it with default values
        foreach ($simulations as $name => $sim) {
            if (!isset($versions[$name])) {
                continue; // eg. when key MEL/SEL is null
            }
            $version = $versions[$name];
            $sql = <<<"SQL"
                INSERT IGNORE INTO simulation (watchdog_id, name, version)
                SELECT
                    `id` as `watchdog_id`,
                    '{$name}' as `name`,
                    '{$version}' as `version`
                FROM watchdog                        
                SQL;
            $this->addSql($sql);
        }
    }

    /**
     * @return void
     */
    public function insertWatchdogRecord(): void
    {
        // insert watchdog record given the data from game_session, if it is there
        $watchdogServerId = Watchdog::getInternalServerId()->toBinary();
        $sql = <<<"SQL"
            INSERT INTO watchdog (`server_id`, `status`, `token`)
            SELECT 
                '{$watchdogServerId}' as `server_id`,
                'ready' as `status`,
                UUID_SHORT() as `token`
            FROM game_session
            SQL;
        $this->addSql($sql);

        // phpcs:disable Generic.Files.LineLength.TooLong
        $this->addSql(<<<"SQL"
            INSERT IGNORE INTO watchdog (`server_id`, `status`, `token`) VALUES ('{$watchdogServerId}', 'ready', UUID_SHORT())
            SQL
        );
        // phpcs:enable Generic.Files.LineLength.TooLong
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    /**
     * @throws Exception
     */
    protected function onUp(Schema $schema): void
    {
        // retrieve required SymfonyToLegacyHelper service
        // phpcs:disable Generic.Files.LineLength.TooLong
        $this->container->get(SymfonyToLegacyHelper::class);
        $this->addSql(<<<'SQL'
            CREATE TABLE `watchdog` (
                `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `server_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
                `status` enum('registered','ready','unresponsive') NOT NULL DEFAULT 'registered',
                `token` bigint(20) NOT NULL,
                `deleted_at` datetime DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                `archived` bigint GENERATED ALWAYS AS (if(`deleted_at` is null,0,UNIX_TIMESTAMP(deleted_at))) VIRTUAL COMMENT 'GENERATED ALWAYS AS (if(`deleted_at` is null,0,UNIX_TIMESTAMP(deleted_at))) VIRTUAL',
                UNIQUE KEY `uq_server_id_archived` (`server_id`,`archived`)
            )
            SQL
        );
        // phpcs:enable Generic.Files.LineLength.TooLong

        // phpcs:disable Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
            CREATE TABLE `simulation` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `watchdog_id` int(10) unsigned NOT NULL,
              `name` varchar(255) NOT NULL,
              `version` varchar(255) DEFAULT NULL,
              `last_month` int(11) NOT NULL DEFAULT -2,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_watchdog_id_name` (`watchdog_id`,`name`),
              CONSTRAINT `fk_watchdog_id` FOREIGN KEY (`watchdog_id`) REFERENCES `watchdog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL
        );
        // phpcs:enable Generic.Files.LineLength.TooLong

        $this->insertWatchdogRecord();
        $this->insertSimulationRecords();
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE `game_session` DROP `game_session_watchdog_address`, DROP `game_session_watchdog_token`');
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE game DROP COLUMN game_mel_lastupdate, DROP COLUMN game_cel_lastupdate, DROP COLUMN game_sel_lastupdate, DROP COLUMN game_mel_lastmonth, DROP COLUMN game_cel_lastmonth, DROP COLUMN game_sel_lastmonth');
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE simulation');
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $this->addSql('ALTER TABLE game ADD COLUMN game_mel_lastmonth INT NOT NULL DEFAULT -1 AFTER game_eratime, ADD COLUMN game_cel_lastmonth INT NOT NULL DEFAULT -1 AFTER game_mel_lastmonth, ADD COLUMN game_sel_lastmonth INT NOT NULL DEFAULT -1 AFTER game_cel_lastmonth, ADD COLUMN game_mel_lastupdate double NULL DEFAULT NULL AFTER game_sel_lastmonth, ADD COLUMN game_cel_lastupdate double NULL DEFAULT NULL AFTER game_mel_lastupdate, ADD COLUMN game_sel_lastupdate double NULL DEFAULT NULL AFTER game_cel_lastupdate');
        $this->addSql(<<<'SQL'
            ALTER TABLE `game_session`
            ADD `game_session_watchdog_address` varchar(255) NOT NULL DEFAULT '' FIRST,
            ADD `game_session_watchdog_token` bigint NOT NULL AFTER `game_session_watchdog_address`
            SQL
        );
    }
}
