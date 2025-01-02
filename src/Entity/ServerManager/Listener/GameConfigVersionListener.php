<?php

namespace App\Entity\ServerManager\Listener;

use App\Domain\Common\EntityEnums\GameConfigVersionVisibilityValue;
use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\User;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Security;

class GameConfigVersionListener
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Security $security
    ) {
    }

    /**
     * @throws \Exception
     */
    public function postLoad(GameConfigVersion $gameConfigVersion, PostLoadEventArgs $event): void
    {
        $contents = $this->getConfigFileContents($gameConfigVersion->getFilePath());
        $gameConfigVersion->setGameConfigCompleteRaw($contents[0]);
        $gameConfigVersion->setGameConfigComplete($contents[1]);
        $gameConfigVersion->setUploadUserName(
            $gameConfigVersion->getUploadUser() == 1 ? 'BUas (at installation)' :
            $event->getObjectManager()->getRepository(User::class)
                ->find($gameConfigVersion->getUploadUser())->getUsername()
        );
    }

    public function prePersist(GameConfigVersion $gameConfigVersion, PrePersistEventArgs $event): void
    {
        if (is_null($gameConfigVersion->getVisibility())) {
            $gameConfigVersion->setVisibility(new GameConfigVersionVisibilityValue('active'));
        }
        if (is_null($gameConfigVersion->getUploadTime())) {
            $gameConfigVersion->setUploadTime(time());
        }
        if (is_null($gameConfigVersion->getUploadUser())) {
            $gameConfigVersion->setUploadUser($this->security->getUser()->getId());
        }
        if (is_null($gameConfigVersion->getFilePath())) {
            $fileName = $gameConfigVersion->getGameConfigFile()->getFilename();
            $gameConfigVersion->setFilePath(
                "{$fileName}/{$fileName}_{$gameConfigVersion->getVersion()}.json"
            );
            $contents = $this->getConfigFileContents($gameConfigVersion->getFilePath());
            $gameConfigVersion->setGameConfigCompleteRaw($contents[0]);
            $gameConfigVersion->setGameConfigComplete($contents[1]);
        }
        if (is_null($gameConfigVersion->getRegion())) {
            $gameConfigVersion->setRegion(
                $gameConfigVersion->getGameConfigComplete()['datamodel']['region']
            );
        }
        if (is_null($gameConfigVersion->getClientVersions())) {
            $gameConfigVersion->setClientVersions('Any');
        }
        if (is_null($gameConfigVersion->getLastPlayedTime())) {
            $gameConfigVersion->setLastPlayedTime(0);
        }
    }

    public function getConfigFileContents($path): array
    {
        $path = "{$this->kernel->getProjectDir()}/ServerManager/configfiles/{$path}";
        $gameConfigContentCompleteRaw = file_get_contents($path);
        $gameConfigContentComplete = json_decode($gameConfigContentCompleteRaw, true);
        if ($gameConfigContentComplete === false) {
            throw new \Exception(
                "Cannot read contents of the session's chosen configuration file: {$path}"
            );
        }
        return [$gameConfigContentCompleteRaw, $gameConfigContentComplete];
    }
}
