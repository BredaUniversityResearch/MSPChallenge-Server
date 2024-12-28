<?php
namespace App\Tests\ServerManager;

use App\Domain\Common\GameListAndSaveSerializer;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GameListSaveSerializationTest extends KernelTestCase
{

    public static function testSerializations(): void
    {
        GameListCreationTest::testGameListCreation();
        $container = static::getContainer();
        $emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");
        $gameListRepo = $emServerManager->getRepository(GameList::class);
        $gameList = $gameListRepo->find(1);
        self::assertInstanceOf(GameList::class, $gameList, 'Could not retrieve test GameList object');
        
        // testing related to hitting the save button
        $serializer = new GameListAndSaveSerializer($emServerManager);
        $gameListArray = $serializer->createDataFromGameList($gameList);
        self::assertIsArray($gameListArray, 'createDataFromGameList failed');

        $gameSave = $serializer->createGameSaveFromData($gameListArray);
        $json = $serializer->createJsonFromGameSave($gameSave);
        self::assertJson($json, 'createJsonFromGameSave failed');

        $gameSave = $serializer->createGameSaveFromData($gameListArray);
        self::assertInstanceOf(GameSave::class, $gameSave, 'createGameSaveFromData failed');

        $emServerManager->persist($gameSave);
        $emServerManager->flush();

        // testing related to hitting the load button
        $gameSaveArray = $serializer->createDataFromGameSave($gameSave);
        self::assertIsArray($gameSaveArray, 'createDataFromGameSave failed');
        
        $gameListNew = $serializer->createGameListFromData($gameSaveArray);
        self::assertInstanceOf(GameList::class, $gameListNew, 'createGameListFromData failed');

        $emServerManager->persist($gameListNew);
        $emServerManager->flush();

        // testing related to uploading a save ZIP (1)
        $gameSaveNew = $serializer->createGameSaveFromJson($json);
        self::assertInstanceOf(GameSave::class, $gameSaveNew, 'createGameSaveFromJson failed');

        $emServerManager->persist($gameSaveNew);
        $emServerManager->flush();

        // testing related to uploading a save ZIP (2)
        $oldGameListJson = '{
            "name":"North Sea",
            "game_config_version_id":1,
            "game_server_id":1,
            "game_geoserver_id":1,
            "watchdog_server_id":1,
            "game_creation_time":1713425645,
            "game_start_year":2018,
            "game_end_month":384,
            "game_current_month":0,
            "game_running_til_time":1713828103,
            "password_admin":"eyJhZG1pbiI6eyJwcm92aWRlciI6IkFwcFxcRG9tYWluXFxBUElcXHYxXFxBdXRoX01TUCIsInZhbHVlIjoibXNwYWRtaW58QW5kcmVhTW9yZiJ9LCJyZWdpb24iOnsicHJvdmlkZXIiOiJBcHBcXERvbWFpblxcQVBJXFx2MVxcQXV0aF9NU1AiLCJ2YWx1ZSI6Im1zcGFkbWlufEFuZHJlYU1vcmYifX0=",
            "password_player":"eyJwcm92aWRlciI6ImxvY2FsIiwidmFsdWUiOnsiMyI6ImdyZWVuZ3Jhc3NvZmhvbWUiLCI0IjoicmVkdmVsdmV0Y2FrZSIsIjUiOiJwaW5rcm9zZXMiLCI2IjoiYmx1ZXN1ZWRlc2hvZXMiLCI3Ijoib3JhbmdlYWJvdmUiLCI4IjoiaW50aGVuYXZ5IiwiOSI6InRoZXllbGxvd3N1Ym1hcmluZSIsIjEwIjoicHVycGxlaGF6ZSJ9fQ==",
            "session_state":"healthy",
            "game_state":"pause",
            "game_visibility":"public",
            "players_active":0,
            "players_past_hour":1,
            "demo_session":0,
            "api_access_token":"8363981577164041601",
            "server_version":"4.0.1",
            "id":1,
            "game_config_files_filename":"North_Sea_basic",
            "game_config_versions_region":"northsee"
        }';
        $gameSaveNew2 = $serializer->createGameSaveFromJson($oldGameListJson);
        self::assertInstanceOf(GameSave::class, $gameSaveNew2, 'createGameSaveFromJson with old data failed');
        $emServerManager->persist($gameSaveNew2);
        $emServerManager->flush();
    }

    public static function setUpBeforeClass(): void
    {
        SetupBeforeTests::completeCleanInstallDatabases();
    }
}
