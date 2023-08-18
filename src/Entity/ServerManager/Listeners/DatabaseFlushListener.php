<?php

namespace App\Entity\ServerManager\Listeners;

use App\Domain\API\v1\Game;
use App\Entity\ServerManager\GameList;
use Doctrine\ORM\Event\OnFlushEventArgs;

class DatabaseFlushListener
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $updatedEntities = $unitOfWork->getScheduledEntityUpdates();
        foreach ($updatedEntities as $updatedEntity) {
            if ($updatedEntity instanceof GameList) {
                $changeset = $unitOfWork->getEntityChangeSet($updatedEntity);
                if (!empty($changeset['gameState'][1])) {
                    $game = new Game();
                    $game->setGameSessionId($updatedEntity->getId());
                    $game->State($changeset['gameState'][1]);
                }
            }
        }
    }
}
