<?php
namespace App\Tests\ServerManager;

use App\Domain\Common\EntityEnums\GameConfigVersionVisibilityValue;
use App\Entity\ServerManager\GameConfigFile;
use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\GameList;
use App\Message\GameList\GameListCreationMessage;
use App\MessageHandler\GameList\GameListCreationMessageHandler;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
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
                $gameConfigVersion->setLastPlayedTime('test');
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
        // completely removes, creates and migrates the test databases

        $app = new Application(static::bootKernel());
        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--connection' => $_ENV['DBNAME_SERVER_MANAGER'],
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());

        $app = new Application(static::bootKernel());
        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--connection' => 'msp_session_1', // don't worry, only removes msp_session_1_test database
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());

        $input2 = new ArrayInput([
            'command' => 'doctrine:database:create',
            '--connection' => $_ENV['DBNAME_SERVER_MANAGER']
        ]);
        $input2->setInteractive(false);
        $app->doRun($input2, new NullOutput());

        $input3 = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--em' => $_ENV['DBNAME_SERVER_MANAGER'],
        ]);
        $input3->setInteractive(false);
        $app->doRun($input3, new NullOutput());

        $input4 = new ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--em' => $_ENV['DBNAME_SERVER_MANAGER'],
            '--append' => true
        ]);
        $input4->setInteractive(false);
        $app->doRun($input4, new NullOutput());
    }
}