<?php

namespace App\Entity\ServerManager\Listener;

use App\Domain\Common\EntityEnums\GameSaveTypeValue;
use App\Domain\Common\EntityEnums\GameSaveVisibilityValue;
use App\Entity\ServerManager\GameSave;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\GameWatchdogServer;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;

class GameSaveListener
{

    public function preFlush(GameSave $gameSave, PreFlushEventArgs $event): void
    {
        $gameSave->encodePasswords();
    }

    public function postLoad(GameSave $gameSave, PostLoadEventArgs $event): void
    {
        $gameSave->decodePasswords();
    }

    public function prePersist(GameSave $gameSave, PrePersistEventArgs $event): void
    {
        if (is_null($gameSave->getSaveType())) {
            $gameSave->setSaveType(new GameSaveTypeValue('full'));
        }
        if (is_null($gameSave->getSaveVisibility())) {
            $gameSave->setSaveVisibility(new GameSaveVisibilityValue('active'));
        }
        if (is_null($gameSave->getGameServer())) {
            $gameSave->setGameServer(
                $event->getObjectManager()->getRepository(GameServer::class)->find(1)
            );
        }
        if (is_null($gameSave->getGameWatchdogServer())) {
            $gameSave->setGameWatchdogServer(
                $event->getObjectManager()->getRepository(GameWatchdogServer::class)->find(1)
            );
        }
    }
}
