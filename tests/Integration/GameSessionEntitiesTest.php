<?php
namespace App\Tests\Integration;

use App\Domain\Services\ConnectionManager;
use App\Entity\Country;
use App\Entity\Game;
use App\Entity\Layer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class GameSessionEntitiesTest extends KernelTestCase
{
    private const DBNAME = 'msp_session_1';
    private EntityManagerInterface $em;

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
    }


    private function start(): void
    {
        $container = static::getContainer();
        $test = self::DBNAME;
        $this->em = $container->get("doctrine.orm.{$test}_entity_manager");
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
