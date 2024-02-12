<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230201140525 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'Add game_mel_lastupdate, game_sel_lastupdate, game_cel_lastupdate columns to game, '.
            'change default value for game_currentmonth to -1';
    }

    protected function getDatabaseType(): ?MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    protected function onUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'ALTER TABLE `game`
            ADD `game_mel_lastupdate` double NULL AFTER `game_sel_lastmonth`,
            ADD `game_cel_lastupdate` double NULL AFTER `game_mel_lastupdate`,
            ADD `game_sel_lastupdate` double NULL AFTER `game_cel_lastupdate`'
        );
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            "ALTER TABLE `game` CHANGE `game_currentmonth` `game_currentmonth` int(11) NULL DEFAULT '-1' AFTER `game_lastupdate`"
        );
    }

    protected function onDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'ALTER TABLE `game` DROP IF EXISTS `game_mel_lastupdate`, DROP IF EXISTS `game_cel_lastupdate`, DROP IF EXISTS `game_sel_lastupdate`'
        );
        // phpcs:ignoreFile Generic.Files.LineLength.TooLong
        $this->addSql(
            "ALTER TABLE `game` CHANGE `game_currentmonth` `game_currentmonth` int(11) NULL DEFAULT 0 AFTER `game_lastupdate`"
        );
    }
}
