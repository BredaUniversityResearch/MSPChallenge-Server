<?php

declare(strict_types=1);

namespace <namespace>;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class <className> extends MSPMigration
{
    public function getDescription(): string
    {
        return '';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        // E.g. To restrict to only game session dbs, use:
        //   return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
        return null;
    }

    protected function onUp(Schema $schema): void
    {
        // this onUp() migration is auto-generated, please modify it to your needs
<up>
    }

    protected function onDown(Schema $schema): void
    {
        // this onDown() migration is auto-generated, please modify it to your needs
<down>
    }<override>
}
