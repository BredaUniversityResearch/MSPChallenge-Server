<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240402190125 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add first default policy type and filter type data. Nullable policy value.';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);

    }

    protected function onUp(Schema $schema): void
    {
        // using replace instead of insert to prevent any conflicting data from previous migration versions, of which the inserts have moved here
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
    INSERT INTO `policy_type` (`id`, `name`, `display_name`, `data_type`) VALUES
    (3,	'ecological_fishing_gear',	'Ecological fishing gear',	'boolean');
    SQL
        );

    }

    protected function onDown(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            <<< 'SQL'
    DELETE FROM `policy_type` WHERE `id` = 3;
    SQL
        );
    }
}
