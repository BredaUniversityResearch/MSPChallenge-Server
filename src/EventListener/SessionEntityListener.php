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
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

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

    /**
     * @throws \Exception
     */
    public function postLoad(PostLoadEventArgs $event): void
    {
        if ($event->getObject() instanceof Game) {
            $this->postLoadGame($event);
        }
    }

    private function prePersistGame(PrePersistEventArgs $event): void
    {
        $game = $event->getObject();

        $game->setGameEratime(max($game->getGameEratime(), $this->params->get('app.min_game_era_time')));
        $game->setGameAutosaveMonthInterval($this->params->get('app.game_auto_save_interval'));
        $game->setGameIsRunningUpdate(0);
    }

    /**
     * @throws \Exception
     */
    private function postLoadGame(PostLoadEventArgs $event): void
    {
        $game = $event->getObject();

        $runningConfigPath = $this->params->get('app.session_config_dir').$game->getGameConfigfile();
        $fileSystem = new Filesystem();
        if ($fileSystem->exists($runningConfigPath)) {
            $gameConfigContentCompleteRaw = file_get_contents($runningConfigPath);
            $gameConfigContentComplete = json_decode($gameConfigContentCompleteRaw, true);
            if ($gameConfigContentComplete === false) {
                throw new \Exception(
                    "Cannot read contents of the session's running configuration file: {$runningConfigPath}"
                );
            }
            $game->setRunningGameConfigFileContentsRaw($gameConfigContentCompleteRaw);
            $game->setRunningGameConfigFileContents($gameConfigContentComplete);
        }
    }
}
