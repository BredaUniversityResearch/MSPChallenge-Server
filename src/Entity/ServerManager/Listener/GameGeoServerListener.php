<?php

namespace App\Entity\ServerManager\Listener;

use App\Domain\Security\FieldEncryptor;
use App\Entity\ServerManager\GameGeoServer;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;

readonly class GameGeoServerListener
{
    public function __construct(private FieldEncryptor $encryptor)
    {
    }

    /**
     * After an entity is loaded from the database, decrypt the credential columns into
     * the plain-text runtime properties so the rest of the application always works with
     * clear text.
     */
    public function postLoad(GameGeoServer $geoServer, PostLoadEventArgs $event): void
    {
        $geoServer->setUsername($this->encryptor->decrypt($geoServer->getEncryptedUsername()));
        $geoServer->setPassword($this->encryptor->decrypt($geoServer->getEncryptedPassword()));
    }

    /**
     * Before a flush, ensure the encrypted DB columns are up-to-date.
     *
     * We compare the current plain-text value against the decrypted stored value so that
     * we only produce a new ciphertext (and thus an UPDATE) when the credential actually
     * changed.  This prevents spurious UPDATEs on every flush for unmodified entities.
     */
    public function preFlush(GameGeoServer $geoServer, PreFlushEventArgs $event): void
    {
        // Normalise: ensure the address ends with a slash.
        if (substr($geoServer->getAddress(), -1) !== '/') {
            $geoServer->setAddress($geoServer->getAddress() . '/');
        }

        // Only re-encrypt when the plain-text value is non-empty AND differs from what's stored.
        // An empty plain-text value means the user left the field blank on the form (i.e. "unchanged"),
        // so we leave the existing encrypted column untouched.
        if (!empty($geoServer->getUsername()) &&
            $this->encryptor->decrypt($geoServer->getEncryptedUsername()) !== $geoServer->getUsername()) {
            $geoServer->setEncryptedUsername($this->encryptor->encrypt($geoServer->getUsername()));
        }

        if (!empty($geoServer->getPassword()) &&
            $this->encryptor->decrypt($geoServer->getEncryptedPassword()) !== $geoServer->getPassword()) {
            $geoServer->setEncryptedPassword($this->encryptor->encrypt($geoServer->getPassword()));
        }
    }
}
