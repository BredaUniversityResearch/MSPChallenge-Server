<?php

namespace App\Entity\ServerManager\Listener;

use App\Domain\Common\EntityEnums\GameConfigVersionVisibilityValue;
use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\User;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use Exception;
use Symfony\Component\HttpKernel\KernelInterface;

class GameConfigVersionListener
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Security $security
    ) {
    }

    private function setup(GameConfigVersion $gameConfigVersion): void
    {
        if (is_null($gameConfigVersion->getFilePath())) {
            $fileName = $gameConfigVersion->getGameConfigFile()->getFilename();
            $gameConfigVersion->setFilePath(
                "{$fileName}/{$fileName}_{$gameConfigVersion->getVersion()}.json"
            );
        }
        $this->setupLazyLoaders($gameConfigVersion);
    }

    private function setupLazyLoaders(GameConfigVersion $gameConfigVersion): void
    {
        $gameConfigVersion->hasLazyLoader(
            GameConfigVersion::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE_RAW
        ) or
        $gameConfigVersion->setLazyLoader(
            GameConfigVersion::LAZY_LOADING_PROPERTY_GAME_CONFIG_COMPLETE_RAW,
            function () use ($gameConfigVersion) {
                $path = $this->kernel->getProjectDir().'/ServerManager/configfiles/'.$gameConfigVersion->getFilePath();
                if (false === $contents = file_get_contents($path)) {
                    throw new Exception(
                        "Cannot read contents of the session's chosen configuration file: {$path}"
                    );
                }
                return $contents;
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

    /**
     * @throws Exception
     */
    public function postLoad(GameConfigVersion $gameConfigVersion, PostLoadEventArgs $event): void
    {
        $this->setup($gameConfigVersion);
        // todo: create lazy loader
        $gameConfigVersion->setUploadUserName(
            $gameConfigVersion->getUploadUser() == 1 ? 'BUas (at installation)' :
            $event->getObjectManager()->getRepository(User::class)
                ->find($gameConfigVersion->getUploadUser())->getUsername()
        );
    }


    public function prePersist(GameConfigVersion $gameConfigVersion, PrePersistEventArgs $event): void
    {
        $this->setup($gameConfigVersion);
        if (is_null($gameConfigVersion->getVisibility())) {
            $gameConfigVersion->setVisibility(new GameConfigVersionVisibilityValue('active'));
        }
        if (is_null($gameConfigVersion->getUploadTime())) {
            $gameConfigVersion->setUploadTime(time());
        }
        if (is_null($gameConfigVersion->getUploadUser())) {
            // @phpstan-ignore-next-line "Call to an undefined method"
            $gameConfigVersion->setUploadUser($this->security->getUser()->getId());
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
}
