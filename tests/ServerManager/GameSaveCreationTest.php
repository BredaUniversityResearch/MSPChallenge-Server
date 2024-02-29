<?php
namespace App\Tests\ServerManager;

use App\Domain\Common\EntityEnums\GameSaveTypeValue;
use App\Domain\Common\EntityEnums\GameSaveVisibilityValue;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\GameWatchdogServer;
use App\MessageHandler\GameSave\GameSaveCreationMessageHandler;
use App\Message\GameSave\GameSaveCreationMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class GameSaveCreationTest extends KernelTestCase
{

    /**
     * @throws ExceptionInterface
     */
    public function testGameSaveCreation(): void
    {
        //GameListCreationTest::testGameListCreation();
        //GameListCreationTest::testGameListCreationMessageHandler();

        $container = static::getContainer();
        $emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");

        $normalizeContext = [
            AbstractNormalizer::CALLBACKS => [
                'sessionState' => fn($innerObject) => ((string) $innerObject),
                'gameState' => fn($innerObject) => ((string) $innerObject),
                'gameVisibility' => fn($innerObject) => ((string) $innerObject)
            ]
        ];
        $denormalizeContext = [
            AbstractNormalizer::CALLBACKS => [
                'gameConfigVersion' => fn($innerObject) => $emServerManager->getRepository(
                    GameConfigVersion::class
                )->find($innerObject['id']),
                'gameServer' => fn($innerObject) => $emServerManager->getRepository(
                    GameServer::class
                )->find($innerObject['id']),
                'gameWatchdogServer' => fn($innerObject) => $emServerManager->getRepository(
                    GameWatchdogServer::class
                )->find($innerObject['id']),
                'sessionState' => fn($innerObject) => new GameSessionStateValue($innerObject),
                'gameState' => fn($innerObject) => new GameStateValue($innerObject),
                'gameVisibility' => fn($innerObject) => new GameVisibilityValue($innerObject)
            ],
        ];
        $normalizers = [new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter())];
        $serializer = new Serializer($normalizers, []);

        $gameList = $emServerManager->getRepository(GameList::class)->find(1);

        $normalizedGameList = $serializer->normalize($gameList, null, $normalizeContext);
        $gameSave = $serializer->denormalize($normalizedGameList, GameSave::class, null, $denormalizeContext);
        $gameSave->setGameConfigFilesFilename($gameSave->getGameConfigVersion()->getGameConfigFile()?->getFilename());
        $gameSave->setGameConfigVersionsRegion($gameSave->getGameConfigVersion()?->getRegion());
        $gameSave->setSaveType(new GameSaveTypeValue('full'));
        $gameSave->setSaveVisibility(new GameSaveVisibilityValue('active'));
        $emServerManager->persist($gameSave);
        $emServerManager->flush();
        //self::assertCount(1, $emServerManager->getRepository(GameSave::class)->findAll());
        $handler = $container->get(GameSaveCreationMessageHandler::class);
        $handler->__invoke(new GameSaveCreationMessage(1, 1));
    }

    public static function setUpBeforeClass(): void
    {
        //GameListCreationTest::setUpBeforeClass();
    }
}
