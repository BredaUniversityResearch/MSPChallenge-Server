<?php

namespace App\Entity\ServerManager\Listener;

use App\Entity\ServerManager\GameConfigVersion;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Symfony\Component\HttpKernel\KernelInterface;

class GameConfigVersionListener
{
    public function __construct(
        private readonly KernelInterface $kernel
    ) {
    }

    /**
     * @throws \Exception
     */
    public function postLoad(GameConfigVersion $gameConfigVersion, PostLoadEventArgs $event): void
    {
        // todo: not needed anymore?
        $path = "{$this->kernel->getProjectDir()}/ServerManager/configfiles/{$gameConfigVersion->getFilePath()}";
        $gameConfigContentCompleteRaw = file_get_contents($path);
        $gameConfigContentComplete = json_decode($gameConfigContentCompleteRaw, true);
        if ($gameConfigContentComplete === false) {
            throw new \Exception(
                "Cannot read contents of the session's chosen configuration file: {$path}"
            );
        }
        $gameConfigVersion->setGameConfigCompleteRaw($gameConfigContentCompleteRaw);
        $gameConfigVersion->setGameConfigComplete($gameConfigContentComplete);
    }
}
