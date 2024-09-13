<?php

namespace App\Entity\ServerManager\Listener;

use App\Entity\ServerManager\GameSave;
use Doctrine\ORM\Event\PrePersistEventArgs;

class GameSaveListener
{
    public function prePersist(GameSave $gameSave, PrePersistEventArgs $event): void
    {
        $gameSave->setGameConfigFilesFilename($gameSave->getGameConfigVersion()->getGameConfigFile()?->getFilename());
        $gameSave->setGameConfigVersionsRegion($gameSave->getGameConfigVersion()?->getRegion());
    }
}
