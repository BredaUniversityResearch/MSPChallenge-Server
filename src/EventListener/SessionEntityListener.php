<?php
// Important!
// The reason this isn't split over multiple Doctrine Entity listeners, is because then the services.yaml config
// would have needed a specification of the entity manager in use for said listener (which is impossible):
// App\Entity\Listener\GameListener:
//  tags:
//   - { name: 'doctrine.orm.entity_listener',
//       event: 'prePersist',
//       entity: 'App\Entity\Game',
//       entity_manager: 'msp_session_1'
//     }

namespace App\EventListener;

use App\Entity\Game;
use App\Entity\Layer;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SessionEntityListener
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly LoggerInterface $gameSessionLogger
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        if ($event->getObject() instanceof Game) {
            $this->prePersistGame($event);
        } elseif ($event->getObject() instanceof Layer) {
            $this->prePersistLayer($event);
        }
    }

    private function prePersistGame(PrePersistEventArgs $event): void
    {
        $game = $event->getObject();

        $game->setGameEratime(max($game->getGameEratime(), $this->params->get('app.min_game_era_time')));
        $game->setGameAutosaveMonthInterval($this->params->get('app.game_auto_save_interval'));
        $game->setGameIsRunningUpdate(0);
    }

    private function prePersistLayer(PrePersistEventArgs $event): void
    {
        $layer = $event->getObject();
        if (is_null($layer->getContextCreatingGameSession())) {
            return;
        }
        $geometryCoordsDataSets = [];
        foreach ($layer->getGeometry() as $geometry) {
            $array = [
                'coords' => $geometry->getGeometryGeometry(),
                'data' => $geometry->getGeometryData()
            ];
            if (in_array($array, $geometryCoordsDataSets)) {
                $this->gameSessionLogger->warning(
                    'Avoided adding duplicate geometry (based on the combination of coordinates and complete '.
                    'properties set) to layer {layer}. Some information about the geometry: {geometry}',
                    [
                        'gameSession' => $layer->getContextCreatingGameSession(),
                        'layer' => "{$layer->getLayerName()}",
                        'geometry' => substr($geometry->getGeometryGeometry(), 0, 50).'... - '.
                            substr($geometry->getGeometryData(), 0, 50).'...'
                    ]
                );
                $layer->removeGeometry($geometry);
            } else {
                $geometryCoordsDataSets[] = $array;
            }
        }
    }
}
