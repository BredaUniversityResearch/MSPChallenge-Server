<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\API\v1\Game;
use App\Domain\API\v1\Simulations;
use App\Domain\Common\InternalSimulationName;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\Watchdog;
use Doctrine\DBAL\Schema\Schema;
use App\Entity\Simulation;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

// todo : see https://github.com/doctrine/DoctrineMigrationsBundle/issues/521
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
     * @throws \Exception
     */
    public function insertSimulationRecords(): void
    {
        $result = preg_match('/msp_session_(\d+)/', $this->connection->getDatabase(), $matches);
        $this->abortIf($result !== 1, 'Database name does not match the expected format: msp_session_{id}');

        $game = new Game();
        $game->setGameSessionId((int)$matches);
        try {
            $config = $game->GetGameConfigValues();
        } catch (\Exception $e) {
            $this->warnIf(true, 'Failed to retrieve game configuration values. Skipping simulation registration, error: ' . $e->getMessage());
            return; // nothing to do
        }

        // filter possible internal simulations with the ones present in the config
        $simulations = array_intersect_key(array_flip(array_map(fn(InternalSimulationName $e) => $e->value, InternalSimulationName::cases())), $config);
        if (empty($simulations)) {
            $this->warnIf(true, 'No simulations found to register in game configuration');
            return; // no configured simulations
        }
        $sim = new Simulations();
        $sim->setGameSessionId((int)$matches);
        $versions = $sim->GetConfiguredSimulationTypes();
        $simId = 1;
        foreach ($versions as $name => $version) {
            $nameLowered = strtolower($name);
            $sql = <<<"SQL"
                INSERT INTO simulation (id, watchdog_id, name, version, last_month)
                SELECT
                    '{$simId}' as `id`,
                    '1' as `watchdog_id`,
                    '{$name}' as `name`,
                    '{$version}' as `version`,
                    game_{$nameLowered}_lastmonth as last_month
                FROM game
                SQL;
            $this->addSql($sql);
            $simId++;
        }
        // in-case there is no game record for the simulation, insert it with default values
    $this->addSql(
            'INSERT IGNORE INTO simulation (watchdog_id, name, version) VALUES ' . implode(',', array_map(
                function (string $name) use ($versions) {
                    return "(1, '$name','{$versions[$name]}')";
                },
                array_keys($simulations),
                $simulations
            ))
        );
    }

    /**
     * @return void
     */
    public function insertWatchdogRecord(): void
    {
        // insert watchdog record given the data from game_session, if it is there
        $watchdogServerId = Watchdog::getInternalServerId()->toBinary();
        $watchdogPort = (int)($_ENV['WATCHDOG_PORT'] ?? 45000);
        $watchdogAddress = $_ENV['WATCHDOG_ADDRESS'] ?? '';
        $sql = <<<"SQL"
            INSERT INTO watchdog (`id`,`server_id`, `address`, `port`, `status`, `token`)
            SELECT 
                '1' as `id`,
                '{$watchdogServerId}' as `server_id`,
                IF('{$watchdogAddress}'='',`game_session_watchdog_address`,'{$watchdogAddress}') as `address`,
                {$watchdogPort} as `port`,
                'ready' as `status`,
                UUID_SHORT() as `token`
            FROM game_session
            SQL;
        $this->addSql($sql);

        // insert watchdog record based on environment variables if the previous query did not insert anything
        $watchdogAddress = $_ENV['WATCHDOG_ADDRESS'] ?? 'localhost';
        $this->addSql(<<<"SQL"
            INSERT IGNORE INTO watchdog (`id`,`server_id`, `address`, `port`, `status`, `token`) VALUES (1, '{$watchdogServerId}', '{$watchdogAddress}', {$watchdogPort}, 'ready', UUID_SHORT())
            SQL
        );
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    /**
     * @throws \Exception
     */
    protected function onUp(Schema $schema): void
    {
        // retrieve required SymfonyToLegacyHelper service
        $this->container->get(SymfonyToLegacyHelper::class);
        $this->addSql(<<<'SQL'
            CREATE TABLE `watchdog` (
                `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `server_id` binary(16) NOT NULL COMMENT '(DC2Type:uuid)',
                `address` varchar(255) NOT NULL,
                `port` int NOT NULL DEFAULT '80',
                `scheme` varchar(255) NOT NULL DEFAULT 'http',
                `status` enum('registered','ready','unresponsive','unregistered') NOT NULL DEFAULT 'registered',
                `token` bigint(20) NOT NULL,
                `deleted_at` datetime DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                `archived` tinyint(1) GENERATED ALWAYS AS (if(`deleted_at` is null,0,1)) VIRTUAL,
                UNIQUE KEY `watchdog_id_name_archived` (`server_id`,`archived`)
            )
            SQL
        );
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
            CREATE TABLE `simulation` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `watchdog_id` int(10) unsigned NOT NULL,
              `name` varchar(255) NOT NULL,
              `version` varchar(255) DEFAULT NULL,
              `last_month` int(11) NOT NULL DEFAULT -1,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `watchdog_id_name_archived` (`watchdog_id`,`name`),
              CONSTRAINT `fk_watchdog_id` FOREIGN KEY (`watchdog_id`) REFERENCES `watchdog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL
        );

        $this->insertWatchdogRecord();
        $this->insertSimulationRecords();
        $this->addSql('ALTER TABLE `game_session` DROP `game_session_watchdog_address`, DROP `game_session_watchdog_token`');
        $this->addSql('ALTER TABLE game DROP COLUMN game_mel_lastupdate, DROP COLUMN game_cel_lastupdate, DROP COLUMN game_sel_lastupdate, DROP COLUMN game_mel_lastmonth, DROP COLUMN game_cel_lastmonth, DROP COLUMN game_sel_lastmonth');
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE simulation');
        $this->addSql('ALTER TABLE game ADD COLUMN game_mel_lastmonth INT NOT NULL DEFAULT -1 AFTER game_eratime, ADD COLUMN game_cel_lastmonth INT NOT NULL DEFAULT -1 AFTER game_mel_lastmonth, ADD COLUMN game_sel_lastmonth INT NOT NULL DEFAULT -1 AFTER game_cel_lastmonth, ADD COLUMN game_mel_lastupdate double NULL DEFAULT NULL AFTER game_sel_lastmonth, ADD COLUMN game_cel_lastupdate double NULL DEFAULT NULL AFTER game_mel_lastupdate, ADD COLUMN game_sel_lastupdate double NULL DEFAULT NULL AFTER game_cel_lastupdate');
        $this->addSql(<<<'SQL'
            ALTER TABLE `game_session`
            ADD `game_session_watchdog_address` varchar(255) NOT NULL DEFAULT '' FIRST,
            ADD `game_session_watchdog_token` bigint NOT NULL AFTER `game_session_watchdog_address`
            SQL
        );
    }
}
