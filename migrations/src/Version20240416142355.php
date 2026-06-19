<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;

final class Version20240416142355 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add western baltic sea basin configuration';
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
        // Insert the new game_config_files record
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            "INSERT INTO game_config_files (filename, description) VALUES ('Western_Baltic_Sea_basic', 'Western Baltic Sea basic configuration file supplied by BUas')"
        );

        // Insert the new game_config_version record, using the id of the just-inserted game_config_files row
        // This is safe because the migration is transactional and no concurrent inserts can occur
        $this->addSql(
            "INSERT INTO game_config_version (game_config_files_id, version, version_message, visibility, upload_time, upload_user, last_played_time, file_path, region, client_versions) " .
            "SELECT MAX(id), 1, 'See www.mspchallenge.info', 'active', 1713277131, 1, 0, 'Western_Baltic_Sea_basic/Western_Baltic_Sea_basic_1.json', 'balticline', 'Any' FROM game_config_files WHERE filename = 'Western_Baltic_Sea_basic'"
        );
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
            DELETE FROM `game_config_version` WHERE `filename` = 'Western_Baltic_Sea_basic';
            DELETE FROM `game_config_files` WHERE `file_path` = 'Western_Baltic_Sea_basic/Western_Baltic_Sea_basic_1.json'
SQL
        );
    }
}
