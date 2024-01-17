<?php
// the reason this isn't split over multiple Doctrine Entity listeners, is because then the services.yaml config
// would have needed a specification of the entity manager is use for said listener:
// App\Entity\Listener\GameListener:
//  tags:
//   - { name: 'doctrine.orm.entity_listener',
//       event: 'prePersist',
//       entity: 'App\Entity\Game',
//       entity_manager: 'msp_session_1'
//     }

namespace App\EventListener;

use App\Entity\Game;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SessionEntityListener
{
    public function __construct(
        private readonly ParameterBagInterface $params
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        if ($event->getObject() instanceof Game) {
            $this->prePersistGame($event);
        }
    }

    private function prePersistGame(PrePersistEventArgs $event): void
    {
        $game = $event->getObject();

        $game->setGameEratime(max($game->getGameEratime(), $this->params->get('app.min_game_era_time')));
        $game->setGameConfigfile(sprintf($this->params->get('app.session_config_name'), $game->getGameId()));
        $game->setGameAutosaveMonthInterval($this->params->get('app.game_auto_save_interval'));
        $game->setGameIsRunningUpdate(0);
    }
}
