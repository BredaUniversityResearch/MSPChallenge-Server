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

    public function postLoad(GameConfigVersion $gameConfigVersion, PostLoadEventArgs $event): void
    {
        $path = $this->kernel->getProjectDir().'/ServerManager/configfiles/'.$gameConfigVersion->getFilePath();
        $gameConfigContentComplete = json_decode(
            file_get_contents($path),
            true
        );
        if ($gameConfigContentComplete === false) {
            throw new \Exception(
                'Cannot read contents of the session\'s chosen configuration file: '.$path
            );
        }
        $gameConfigVersion->setGameConfigComplete($gameConfigContentComplete);
    }
}
