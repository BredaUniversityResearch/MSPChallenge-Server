<?php
namespace App\Tests\Integration;

use App\Domain\Services\ConnectionManager;
use App\Entity\Country;
use App\Entity\Game;
use App\Entity\Geometry;
use App\Entity\Layer;
use App\Entity\ServerManager\GameConfigVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class GameSessionEntitiesTest extends KernelTestCase
{
    private const DBNAME = 'msp_session_1';
    private EntityManagerInterface $em;

    private EntityManagerInterface $emServerManager;

    public function testGameSessionEntityManager(): void
    {
        $this->start();
        self::assertInstanceOf(EntityManagerInterface::class, $this->em, 'Not the EntityManager');
    }

    public function testGameEntity(): void
    {
        $this->start();
        $game = new Game();
        $game->setGameId(1);
        $game->setGameStart(2018);
        $game->setGamePlanningGametime(96);
        $game->setGamePlanningRealtime(7200);
        $game->setGamePlanningEraRealtimeComplete();
        $game->setGameEratime(96);
        $this->em->persist($game);
        $this->em->flush();

        $game2 = $this->em->getRepository(Game::class)->retrieve();
        self::assertSame($game, $game2);
    }

    public function testCountryEntity(): void
    {
        $this->start();
        $country = new Country();
        $country->setCountryId(1);
        $country->setCountryColour('#FF00FFFF');
        $country->setCountryIsManager(1);
        $this->em->persist($country);
        $this->em->flush();

        $country2 = $this->em->getRepository(Country::class)->findAll()[0];
        self::assertSame($country, $country2);
    }

    public function testLayerEntity(): void
    {
        $this->start();
        $layer = new Layer();
        $layer->setLayerName('test');
        $layer->setLayerGeotype('raster');
        $layer->setLayerGroup('northsee');
        $layer->setLayerEditable(0);

        $this->em->persist($layer);
        $this->em->flush();

        $layer2 = $this->em->getRepository(Layer::class)->findAll()[0];
        self::assertSame($layer, $layer2);

        $gameConfig = $this->emServerManager->getRepository(GameConfigVersion::class)->find(1);

        $normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        $allLayers = $gameConfig->getGameConfigComplete()['datamodel']['meta'];
        $layer3 = $normalizer->denormalize($allLayers[0], Layer::class);
        dump($layer3);
        self::assertInstanceOf(Layer::class, $layer3); //good enough, normalizer throws exceptions anyway
    }

    public function testGeometryEntity(): void
    {
        $this->start();
        $geometry = new Geometry();
        $geometry->setGeometryGeometry(
            '[[4800176.69845479,748903.878],[4800176.69845479,2483199.127],[7398173.756,2483199.127]]'
        ); // coordinates following a certain projection mode
        $geometry->setGeometryData(
            '{"minx":4800176.698454788,"miny":748903.878,"maxx":7398173.756,"maxy":2483199.127}'
        ); // json representation of feature properties
        $geometry->setGeometryCountryId(1);
        $geometry->setGeometryType('0');
        $geometry->setGeometryMspid('4fba98446ce9d9ff');
        $layer = $this->em->getRepository(Layer::class)->find(1);
        $layer->addGeometry($geometry);
        $this->em->persist($layer);
        $this->em->flush();

        $geometry2 = $this->em->getRepository(Geometry::class)->findAll()[0];
        //dump($layer);
        self::assertSame($geometry, $geometry2);
    }

    private function start(): void
    {
        $container = static::getContainer();
        $test = self::DBNAME;
        $this->em = $container->get("doctrine.orm.{$test}_entity_manager");
        $this->emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");
    }

    public static function setUpBeforeClass(): void
    {
        // completely removes, creates and migrates the test database

        $app = new Application(static::bootKernel());
        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--connection' => 'msp_session_1',
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());

        $input2 = new ArrayInput([
            'command' => 'doctrine:database:create',
            '--connection' => 'msp_session_1'
        ]);
        $input2->setInteractive(false);
        $app->doRun($input2, new NullOutput());

        $input3 = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--em' => 'msp_session_1',
        ]);
        $input3->setInteractive(false);
        $app->doRun($input3, new NullOutput());
    }
}
