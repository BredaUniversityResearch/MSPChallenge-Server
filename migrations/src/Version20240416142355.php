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
        if (1 !== $this->connection->insert('game_config_files', [
            'filename' => 'Western_Baltic_Sea_basic',
            'description' => 'Western Baltic Sea basic configuration file supplied by BUas',
        ])) {
            throw new Exception('Failed to insert game_config_files record');
        }

        $gameConfigFileId = $this->connection->lastInsertId();
        if (1 !== $this->connection->insert('game_config_version', [
            'game_config_files_id' => $gameConfigFileId,
            'version' => 1,
            'version_message' => 'See www.mspchallenge.info',
            'visibility' => 'active',
            'upload_time' => 1713277131,
            'upload_user' => 1,
            'last_played_time' => 0,
            'file_path' => 'Western_Baltic_Sea_basic/Western_Baltic_Sea_basic_1.json',
            'region' => 'balticline',
            'client_versions' => 'Any',
        ])) {
            throw new Exception('Failed to insert game_config_version record');
        }
    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
            DELETE FROM `game_config_version` WHERE `filename` = 'Western_Baltic_Sea_basic';
            DELETE FROM `game_config_files` WHERE `file_path` = 'Western_Baltic_Sea_basic/Western_Baltic_Sea_basic_1.json';
SQL
        );
    }
}
