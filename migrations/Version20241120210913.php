<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20241120210913 extends MSPMigration
{
    public function getDescription(): string
    {
         return 'Insert new config for Eastern med Sea';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        $this->connection->beginTransaction();
        try {
            // phpcs:ignoreFile Generic.Files.LineLength.TooLong
            $this->connection->insert('game_config_files', [
                'filename' => 'Eastern_Med_Sea_basic',
                'description' => 'Eastern Mediterranean Sea basic configuration file supplied by BUas'
            ]);
            $lastInsertedId = $this->connection->lastInsertId();
            $this->connection->insert('game_config_version', [
                'game_config_files_id' => $lastInsertedId,
                'version' => 1,
                'version_message' => 'See www.mspchallenge.info',
                'visibility' => 'active',
                'upload_time' => time(),
                'upload_user' => 1,
                'last_played_time' => 0,
                'file_path' => 'Eastern_Med_Sea_basic/Eastern_Med_Sea_basic_1.json',
                'region' => 'easternmed',
                'client_versions' => 'Any'
            ]);
            // Commit the transaction
            $this->connection->commit();
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            $this->connection->rollBack();
            throw $e;
        }
    }

    protected function onDown(Schema $schema): void
    {
        // Delete dependent records in game_list
        $this->addSql(<<<'SQL'
DELETE FROM `game_list` WHERE `game_config_version_id` IN (SELECT id FROM `game_config_version` WHERE `game_config_files_id` IN (SELECT id FROM `game_config_files` WHERE `filename` = 'Eastern_Med_Sea_basic'))
SQL
        );

        $this->addSql(<<<'SQL'
DELETE FROM `game_config_version` WHERE `game_config_files_id` IN (SELECT id FROM `game_config_files` WHERE `filename` = 'Eastern_Med_Sea_basic')
SQL
        );
        $this->addSql(<<<'SQL'
DELETE FROM `game_config_files` WHERE `filename` = 'Eastern_Med_Sea_basic'
SQL
        );
    }
}
