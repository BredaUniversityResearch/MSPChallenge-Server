<?php

namespace App\Entity\ServerManager\Listener;

use App\Entity\ServerManager\GameConfigVersion;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Exception;
use Symfony\Component\HttpKernel\KernelInterface;

class GameConfigVersionListener
{
    public function __construct(
        private readonly KernelInterface $kernel
    ) {
    }

    /**
     * @throws Exception
     */
    public function postLoad(GameConfigVersion $gameConfigVersion, PostLoadEventArgs $event): void
    {
        $gameConfigVersion->hasLazyLoader(
            GameConfigVersion::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE_RAW
        ) or
        $gameConfigVersion->setLazyLoader(
            GameConfigVersion::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE_RAW,
            function () use ($gameConfigVersion) {
                $path = $this->kernel->getProjectDir().'/ServerManager/configfiles/'.$gameConfigVersion->getFilePath();
                if (false === file_get_contents($path)) {
                    throw new Exception(
                        "Cannot read contents of the session's chosen configuration file: {$path}"
                    );
                }
            }
        );
        $gameConfigVersion->hasLazyLoader(
            GameConfigVersion::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE
        ) or
        $gameConfigVersion->setLazyLoader(
            GameConfigVersion::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE,
            function () use ($gameConfigVersion) {
                $gameConfigContentCompleteRaw = $gameConfigVersion->getGameConfigCompleteRaw();
                $gameConfigContentComplete = json_decode($gameConfigContentCompleteRaw, true);
                if ($gameConfigContentComplete === false) {
                    throw new Exception(
                        'Cannot decode the contents of the session\'s chosen configuration file: '.
                        $gameConfigVersion->getFilePath()
                    );
                }
                return $gameConfigContentComplete;
            }
        );
    }
}
