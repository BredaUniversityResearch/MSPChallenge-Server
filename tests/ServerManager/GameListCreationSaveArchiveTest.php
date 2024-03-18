<?php
namespace App\Tests\ServerManager;

use App\Domain\Common\EntityEnums\GameSaveTypeValue;
use App\Domain\Common\EntityEnums\GameSaveVisibilityValue;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Message\GameList\GameListArchiveMessage;
use App\Message\GameSave\GameSaveLoadMessage;
use App\MessageHandler\GameList\GameListArchiveMessageHandler;
use App\MessageHandler\GameSave\GameSaveCreationMessageHandler;
use App\Message\GameSave\GameSaveCreationMessage;
use App\MessageHandler\GameSave\GameSaveLoadMessageHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class GameListCreationSaveArchiveTest extends KernelTestCase
{

    /**
     * @throws ExceptionInterface
     */
    public function testGameSaveCreation(): void
    {
        GameListCreationTest::testGameListCreation();
        GameListCreationTest::testGameListCreationMessageHandler();

        $container = static::getContainer();
        $emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");

        $normalizers = [new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter())];
        $serializer = new Serializer($normalizers, []);

        $gameList = $emServerManager->getRepository(GameList::class)->find(1);

        $normalizedGameList = $serializer->normalize(
            $gameList,
            null,
            $emServerManager->getRepository(GameList::class)->defaultNormalizeContext()
        );
        $gameSave = $serializer->denormalize(
            $normalizedGameList,
            GameSave::class,
            null,
            $emServerManager->getRepository(GameSave::class)->defaultDenormalizeContext()
        );
        $gameSave->setGameConfigFilesFilename($gameSave->getGameConfigVersion()->getGameConfigFile()?->getFilename());
        $gameSave->setGameConfigVersionsRegion($gameSave->getGameConfigVersion()?->getRegion());
        $gameSave->setSaveType(new GameSaveTypeValue('full'));
        $gameSave->setSaveVisibility(new GameSaveVisibilityValue('active'));

        $gameSave2 = clone $gameSave;
        $gameSave2->setSaveType(new GameSaveTypeValue('layers'));

        $emServerManager->persist($gameSave);
        $emServerManager->persist($gameSave2);
        $emServerManager->flush();
        self::assertCount(2, $emServerManager->getRepository(GameSave::class)->findAll());
    }

    public static function testGameSaveCreationMessageHandler(): void
    {
        $container = static::getContainer();
        $handler = $container->get(GameSaveCreationMessageHandler::class);
        $handler->__invoke(new GameSaveCreationMessage(1, 1));
        $params = $container->get(ContainerBagInterface::class);
        self::assertFileExists(
            $params->get('app.server_manager_save_dir').
            sprintf($params->get('app.server_manager_save_name'), 1)
        );
    }

    public static function testGameSaveLayersCreationMessageHandler(): void
    {
        $container = static::getContainer();
        $handler = $container->get(GameSaveCreationMessageHandler::class);
        $handler->__invoke(new GameSaveCreationMessage(1, 2));
        $params = $container->get(ContainerBagInterface::class);
        self::assertFileExists(
            $params->get('app.server_manager_save_dir').
            sprintf($params->get('app.server_manager_save_name'), 2)
        );
    }

    /**
     * @throws ExceptionInterface
     */
    public static function testGameSaveLoad(): void
    {
        $container = static::getContainer();
        $emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");
        $countGameList = count($emServerManager->getRepository(GameList::class)->findAll());

        $normalizers = [new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter())];
        $serializer = new Serializer($normalizers, []);

        $gameSave = $emServerManager->getRepository(GameSave::class)->find(1);
        $normalizedGameSave = $serializer->normalize(
            $gameSave,
            null,
            $emServerManager->getRepository(GameSave::class)->defaultNormalizeContext()
        );
        $newGameSessionFromLoad = $serializer->denormalize(
            $normalizedGameSave,
            GameList::class,
            null,
            $emServerManager->getRepository(GameList::class)->defaultDenormalizeContext()
        );
        $newGameSessionFromLoad->setName('testReloadIntoSession');
        $newGameSessionFromLoad->setGameSave($gameSave);
        $newGameSessionFromLoad->setPasswordAdmin('test');
        $newGameSessionFromLoad->setSessionState(new GameSessionStateValue('request'));
        $emServerManager->persist($newGameSessionFromLoad);
        $emServerManager->flush();

        $handler = $container->get(GameSaveLoadMessageHandler::class);
        $handler->__invoke(new GameSaveLoadMessage($newGameSessionFromLoad->getId(), 1));
        self::assertCount($countGameList + 1, $emServerManager->getRepository(GameList::class)->findAll());
    }

    public static function testGameSaveReload(): void
    {
        $container = static::getContainer();
        $emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");
        $countGameList = count($emServerManager->getRepository(GameList::class)->findAll());
        $newGameSessionFromLoad = $emServerManager->getRepository(GameList::class)->findOneBy(
            ['name' => 'testReloadIntoSession']
        );
        $handler = $container->get(GameSaveLoadMessageHandler::class);
        $handler->__invoke(new GameSaveLoadMessage($newGameSessionFromLoad->getId(), 1));
        self::assertCount($countGameList, $emServerManager->getRepository(GameList::class)->findAll());
    }

    public static function testGameListArchive(): void
    {
        $container = static::getContainer();
        $emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");
        $gameList = $emServerManager->getRepository(GameList::class)->find(1);
        $gameList->setSessionState(new GameSessionStateValue('archived'));
        $emServerManager->flush();
        $handler = $container->get(GameListArchiveMessageHandler::class);
        $handler->__invoke(new GameListArchiveMessage($gameList->getId()));
        $params = $container->get(ContainerBagInterface::class);
        $connectionManager = $container->get(ConnectionManager::class);
        self::assertFileDoesNotExist(
            $params->get('app.session_config_dir').
            sprintf($params->get('app.session_config_name'), $gameList->getId())
        );
        self::assertDirectoryDoesNotExist(
            $params->get('app.session_raster_dir').$gameList->getId()
        );
        self::assertFalse(in_array('msp_session_1_test', $connectionManager->getDbNames()));
    }

    public static function setUpBeforeClass(): void
    {
        GameListCreationTest::setUpBeforeClass();
    }
}
