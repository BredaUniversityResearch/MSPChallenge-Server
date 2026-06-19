<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415081618 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Remove NS Digitwin config records from game_config_files and game_config_version tables';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
        DELETE from game_config_version WHERE game_config_files_id IN (SELECT id from game_config_files WHERE filename='North_Sea_Digitwin_basic');
        DELETE from game_config_files WHERE filename='North_Sea_Digitwin_basic'
        SQL);
    }

    protected function onDown(Schema $schema): void
    {
        // There is no use to re-insert the deleted records as the config files are not available anymore. If needed, they can be re-created manually.
    }
}
