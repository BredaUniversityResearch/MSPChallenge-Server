<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231127985300 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Adds game_event table';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS `game_event` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_title` varchar(150) NOT NULL,
  `event_body` longtext NOT NULL,
  `event_country_id` int(11) NOT NULL DEFAULT '-1',
  `event_game_month` int(11) NOT NULL DEFAULT '-1',
  `event_datetime` datetime NULL DEFAULT current_timestamp(),
  `event_lastupdate` double NOT NULL DEFAULT '0'
);
SQL);
    }

    public function onDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE game_event');
    }
}
