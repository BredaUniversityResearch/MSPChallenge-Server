<?php

namespace App\Entity\Listener;

use App\Entity\SessionAPI\Game;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

readonly class GameEntityListener implements
    SubEntityListenerInterface,
    PostLoadEventListenerInterface,
    PrePersistEventListenerInterface
{
    public function __construct(
        private ParameterBagInterface $params
    ) {
    }

    public function getSupportedEntityClasses(): array
    {
        return [
            Game::class,
        ];
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $game = $event->getObject();
        if (!$game instanceof Game) {
            return;
        }
        $game->setGameEratime(max($game->getGameEratime(), $this->params->get('app.min_game_era_time')));
        $game->setGameAutosaveMonthInterval($this->params->get('app.game_auto_save_interval'));
        $game->setGameIsRunningUpdate(0);
    }

    /**
     * @throws \Exception
     */
    public function postLoad(PostLoadEventArgs $event): void
    {
        $game = $event->getObject();
        if (!$game instanceof Game) {
            return;
        }
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
