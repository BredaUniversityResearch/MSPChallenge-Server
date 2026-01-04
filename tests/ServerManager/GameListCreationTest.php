<?php
namespace App\Tests\ServerManager;

use App\Domain\Common\EntityEnums\GameConfigVersionVisibilityValue;
use App\Entity\ServerManager\GameConfigFile;
use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\GameList;
use App\Message\GameList\GameListCreationMessage;
use App\MessageHandler\GameList\GameListCreationMessageHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class GameListCreationTest extends KernelTestCase
{
    public static function testGameListCreation(): void
    {
        $container = static::getContainer();
        $emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");
        $configPath = 'tests/ServerManager/config/';
        $path = $container->get('kernel')->getProjectDir()."/{$configPath}";
        $fileSystem = new Filesystem();
        $gameConfigs = [];
        if ($fileSystem->exists($path)) {
            $finder = new Finder();
            foreach ($finder->files()->in($path) as $file) {
                $gameConfig = new GameConfigFile();
                $gameConfig->setFilename($file->getFilename());
                $gameConfig->setDescription('test temporary');
                $gameConfigVersion = new GameConfigVersion();
                $gameConfigVersion->setGameConfigFile($gameConfig);
                $gameConfigVersion->setVersionMessage('test temporary');
                $gameConfigVersion->setFilePath($configPath.$file->getFilename());
                $gameConfigVersion->setVersion(1);
                $gameConfigVersion->setVisibility(new GameConfigVersionVisibilityValue('active'));
                $gameConfigVersion->setUploadUser(1);
                $gameConfigVersion->setUploadTime('temp');
                $gameConfigVersion->setLastPlayedTime(0);
                $gameConfigVersion->setRegion('test');
                $gameConfigVersion->setClientVersions('test');
                $emServerManager->persist($gameConfigVersion);
                $emServerManager->flush();
                $fileSystem->copy(
                    $file->getRealPath(),
                    $container->get('kernel')->getProjectDir() .
                    "/ServerManager/configfiles/{$configPath}{$file->getFilename()}"
                );
                $gameConfigs[] = $gameConfigVersion;
            }
        }
        if (empty($gameConfigs)) {
            $gameConfigs[] = $emServerManager->getRepository(GameConfigVersion::class)->findOneBy(['id' => 1]);
        }
        foreach ($gameConfigs as $gameConfig) {
            $newGameSession = new GameList();
            $newGameSession->setName('testSession');
            $newGameSession->setGameConfigVersion($gameConfig);
            $newGameSession->setPasswordAdmin('test');
            $emServerManager->persist($newGameSession);
            $emServerManager->flush();
        }
        self::assertCount(count($gameConfigs), $emServerManager->getRepository(GameList::class)->findAll());
    }

    public static function testGameListCreationMessageHandler(): void
    {
        $container = static::getContainer();
        $emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");
        foreach ($emServerManager->getRepository(GameList::class)->findAll() as $gameSession) {
            $timePre = microtime(true);
            $handler = $container->get(GameListCreationMessageHandler::class);
            $handler->__invoke(new GameListCreationMessage($gameSession->getId()));
            $timePost = microtime(true);
            self::assertTrue($timePost - $timePre > 10, 'Looks like session creation failed?');
        }
    }

    public static function setUpBeforeClass(): void
    {
        \App\Tests\Utils\ResourceHelper::resetDatabases(static::bootKernel()->getProjectDir());
    }
}
