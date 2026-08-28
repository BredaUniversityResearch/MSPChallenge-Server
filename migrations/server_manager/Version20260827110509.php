<?php

declare(strict_types=1);

namespace DoctrineMigrations\ServerManager;

use App\Domain\Security\FieldEncryptor;
use App\Migration\ContainerAwareMigrationInterface;
use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add "access_type" to game_geoservers, make "username"/"password" nullable and encrypted.
 *
 * Credentials are now stored as authenticated ciphertext (XSalsa20-Poly1305) derived from
 * APP_SECRET (kernel.secret).  The plain-text "ReadUser" / "ReadUser" default credentials
 * are encrypted at migration time using the current APP_SECRET so the stored value is always
 * environment-specific.
 */
final class Version20260827110509 extends MSPMigration implements ContainerAwareMigrationInterface
{
    private ContainerInterface $container;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function getDescription(): string
    {
        return 'Add access_type, remove unique address constraint, and encrypt nullable GeoServer credentials';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_SERVER_MANAGER);
    }

    protected function onUp(Schema $schema): void
    {
        // 1. Schema change – alter table first (queued, runs before the UPDATE below).
        $this->addSql(
            'ALTER TABLE `game_geoservers`
              ADD COLUMN `access_type` VARCHAR(20) NOT NULL DEFAULT \'credentials\' AFTER `address`,
              DROP INDEX `UNIQ_F3B4ECE7D4E6F81`,
              MODIFY COLUMN `username` VARCHAR(512) NULL,
              MODIFY COLUMN `password` VARCHAR(512) NULL'
        );

        // 2. Encrypt the default "ReadUser" credentials using the current APP_SECRET.
        $encryptor = new FieldEncryptor($this->container->getParameter('kernel.secret'));

        $this->addSql(
            'UPDATE `game_geoservers` SET `access_type` = ?, `username` = ?, `password` = ? WHERE `id` = 1',
            ['credentials', $encryptor->encrypt('ReadUser'), $encryptor->encrypt('ReadUser')]
        );
    }

    protected function onDown(Schema $schema): void
    {
        $encryptor = new FieldEncryptor($this->container->getParameter('kernel.secret'));

        // Read all rows IMMEDIATELY (before the queued SQL runs) so we can decrypt the values.
        $rows = $this->connection->fetchAllAssociative('SELECT `id`, `username`, `password` FROM `game_geoservers`');

        foreach ($rows as $row) {
            if ((int) $row['id'] === 1) {
                // Restore the original sentinel values that pointed to Symfony secrets.
                $plainUsername = 'MSPCHALLENGE_GEOSERVER_USERNAME';
                $plainPassword = 'MSPCHALLENGE_GEOSERVER_PASSWORD';
            } else {
                $plainUsername = $encryptor->decrypt($row['username']) ?? '';
                $plainPassword = $encryptor->decrypt($row['password']) ?? '';
            }

            // Queue UPDATE — runs after onDown() returns, before the ALTER TABLE below.
            $this->addSql(
                'UPDATE `game_geoservers` SET `username` = ?, `password` = ? WHERE `id` = ?',
                [$plainUsername, $plainPassword, (int) $row['id']]
            );
        }

        $this->addSql(
            'ALTER TABLE `game_geoservers`
              DROP COLUMN `access_type`,
              ADD UNIQUE INDEX `UNIQ_F3B4ECE7D4E6F81` (`address`),
              MODIFY COLUMN `username` VARCHAR(255) NOT NULL,
              MODIFY COLUMN `password` VARCHAR(255) NOT NULL'
        );
    }
}
