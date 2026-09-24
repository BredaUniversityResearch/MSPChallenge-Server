<?php

declare(strict_types=1);

namespace DoctrineMigrations\ServerManager;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908081457 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Remove config files that have been become restricted to internal use only, and are no longer available for public use.';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    /**
     * @throws Exception
     */
    protected function onUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
          DELETE from game_config_version WHERE id IN (3,5)
        SQL);

        // only remove the game_config_files records if there are no other game_config_version records that reference them

        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
          SELECT f.id, f.filename, v.file_path from game_config_version v inner join game_config_files f on v.game_config_files_id=f.id AND v.file_path NOT IN ('Clyde_marine_region_basic/Clyde_marine_region_basic_1.json','Adriatic_Sea_basic/Adriatic_Sea_basic_1.json') AND f.filename IN ('Clyde_marine_region_basic','Adriatic_Sea_basic')
        SQL);
        $gameConfigFileIds = array_diff([3,5], collect($rows)->pluck('id')->toArray());
        if (empty($gameConfigFileIds)) {
            return;
        }
        $gameConfigFileIds = implode(', ', $gameConfigFileIds);
        $this->addSql(<<<"SQL"
          DELETE from game_config_files WHERE id IN ({$gameConfigFileIds})
        SQL);
    }

    protected function onDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
          INSERT IGNORE INTO `game_config_files` (`id`, `filename`, `description`) VALUES
          (3, 'Clyde_marine_region_basic', 'Clyde marine region basic configuration file supplied by BUas'),
          (5, 'Adriatic_Sea_basic', 'Adriatic Sea basic configuration file supplied by BUas');
        SQL);

        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
          INSERT INTO `game_config_version` (`id`, `game_config_files_id`, `version`, `version_message`, `visibility`, `upload_time`, `upload_user`, `last_played_time`, `file_path`, `region`, `client_versions`) VALUES
          (3, 3, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Clyde_marine_region_basic/Clyde_marine_region_basic_1.json', 'simcelt', 'Any'),
          (5, 5, 1, 'See www.mspchallenge.info', 'active', 1585692000, 1, 0, 'Adriatic_Sea_basic/Adriatic_Sea_basic_1.json', 'adriatic', 'Any');
        SQL
        );
    }
}
