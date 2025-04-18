<?php
namespace App\Tests\Integration;

use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\EntityEnums\PlanState;
use App\Domain\Common\EntityEnums\RestrictionSort;
use App\Domain\Common\EntityEnums\RestrictionType;
use App\Entity\Country;
use App\Entity\EnergyConnection;
use App\Entity\EnergyOutput;
use App\Entity\Fishing;
use App\Entity\Game;
use App\Entity\Geometry;
use App\Entity\Grid;
use App\Entity\GridEnergy;
use App\Entity\Layer;
use App\Entity\Objective;
use App\Entity\Plan;
use App\Entity\PlanDelete;
use App\Entity\PlanLayer;
use App\Entity\PlanMessage;
use App\Entity\PlanRestrictionArea;
use App\Entity\Restriction;
use App\Entity\ServerManager\GameConfigVersion;
use App\Repository\LayerRepository;
use App\Tests\ServerManager\GameListCreationTest;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GameSessionEntitiesTest extends KernelTestCase
{
    private const DBNAME = 'msp_session_1';
    private EntityManagerInterface $em;

    private EntityManagerInterface $emServerManager;

    private ValidatorInterface $validator;

    private ObjectNormalizer $normalizer;

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

    /**
     * @throws \ReflectionException
     * @throws ExceptionInterface
     */
    public function testLayerEntity(): void
    {
        $this->start();
        $layer = new Layer();
        $layer->setLayerName('test');
        $layer->setLayerGeoType(LayerGeoType::POLYGON);
        $layer->setLayerGroup('northsee');
        $layer->setLayerEditable(0);

        $this->em->persist($layer);
        $this->em->flush();
        $layer2 = $this->em->getRepository(Layer::class)->find(1);
        self::assertSame($layer, $layer2);

        $gameConfig = $this->emServerManager->getRepository(GameConfigVersion::class)->find(1);
        $allLayers = $gameConfig->getGameConfigComplete()['datamodel']['meta'];
        /** @var LayerRepository $layerRepo */
        $layerRepo = $this->em->getRepository(Layer::class);
        self::assertInstanceOf(Layer::class, $layerRepo->createLayerFromData($allLayers[0])); //good enough, normalizer throws exceptions anyway

        $planLayer = new Layer();
        $planLayer->setOriginalLayer($layer);
        $this->em->persist($planLayer);
        $this->em->flush();
        $planLayer2 = $this->em->getRepository(Layer::class)->find(2);
        self::assertSame($planLayer2, $planLayer);
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
        $geometry->setCountry(
            $this->em->getRepository(Country::class)->find(1)
        );
        $geometry->setGeometryType('0');
        $geometry->setGeometryMspid('4fba98446ce9d9ff');
        $layer = $this->em->getRepository(Layer::class)->find(1);
        $layer->addGeometry($geometry);
        $this->em->persist($layer);
        $this->em->flush();

        $geometry2 = $this->em->getRepository(Geometry::class)->findAll()[0];
        //dump($layer);
        self::assertSame($geometry, $geometry2);
        $geometry3 = $layer->getGeometry()[0];
        self::assertSame($geometry, $geometry3);
        $geometry2 = clone $geometry;
        $geometry2->setGeometryMspid('1');
        $geometry2->setGeometryData(
            '{"minx":4800176.698454788,"miny":748903.878,"maxx":7398173.756,"maxy":2483199.121}'
        );
        $geometry2->setGeometryToSubtractFrom($geometry);
        $geometry3 = clone $geometry;
        $geometry3->setGeometryData(
            '{"minx":4800176.698454788,"miny":748903.878,"maxx":7398173.756,"maxy":2483199.120}'
        );
        $this->em->persist($geometry2);
        $this->em->persist($geometry3);
        $this->em->flush();
        self::assertSame($geometry->getGeometrySubtractives()[0], $geometry2);

        $geometry4 = clone $geometry;
        $validation = $this->validator->validate($geometry4, new UniqueEntity(
            ['geometryGeometry', 'geometryData'],
            'has to be unique geometry',
            null,
            self::DBNAME
        ));
        self::assertSame(1, $validation->count());
        $this->em->persist($geometry4);
        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    public function testDuplicateGeometryThroughLayerAdd(): void
    {
        $this->start();
        $layer = new Layer();
        $layer->setLayerName('test2');
        $layer->setLayerGeoType(LayerGeoType::POLYGON);
        $layer->setLayerGroup('northsee2');
        $layer->setLayerEditable(0);

        $geometry5 = new Geometry();
        $geometry5->setGeometryGeometry(
            '[[9900176.69845479,748903.878],[4800176.69845479,2483199.127],[7398173.756,2483199.127]]'
        ); // coordinates following a certain projection mode
        $geometry5->setGeometryData(
            '{"minx":9900176.698454788,"miny":748903.878,"maxx":7398173.756,"maxy":2483199.127}'
        ); // json representation of feature properties
        $geometry5->setCountry(
            $this->em->getRepository(Country::class)->find(1)
        );
        $geometry5->setGeometryType('0');
        $geometry5->setGeometryMspid('abcdefg');

        $layer->addGeometry($geometry5);
        $this->em->persist($layer); // SessionEntityListener should have prevented addition of geometry6
        $this->em->flush();

        self::assertCount(4, $this->em->getRepository(Geometry::class)->findAll());
        // namely $geometry, $geometry3, $geometry4, $geometry5 only
    }

    public function testEnergyConnection(): void
    {
        $this->start();
        $energyConnection = new EnergyConnection();
        $energyConnection->setStartGeometry(
            $this->em->getRepository(Geometry::class)->find(1)
        );
        $energyConnection->setEndGeometry(
            $this->em->getRepository(Geometry::class)->find(2)
        );
        $energyConnection->setCableGeometry(
            $this->em->getRepository(Geometry::class)->find(3)
        );
        $energyConnection->setEnergyConnectionStartCoordinates('test coordinates');
        $energyConnection->setEnergyConnectionLastupdate(100);
        $this->em->persist($energyConnection);
        $this->em->flush();
        self::assertSame(
            $this->em->getRepository(Geometry::class)->find(3)->getEnergyConnectionCable()[0],
            $energyConnection
        );
    }

    public function testEnergyOutput(): void
    {
        $this->start();
        $energyOutput = new EnergyOutput();
        $energyOutput->setGeometry($this->em->getRepository(Geometry::class)->find(3));
        $this->em->persist($energyOutput);
        $this->em->flush();
        self::assertSame(
            $this->em->getRepository(Geometry::class)->find(3)->getEnergyOutput()[0],
            $energyOutput
        );
    }

    public function testRestrictionEntity(): void
    {
        $this->start();
        $restriction = new Restriction();
        $restriction->setRestrictionSort(RestrictionSort::INCLUSION);
        $restriction->setRestrictionType(RestrictionType::WARNING);
        $restriction->setRestrictionMessage('Precautionary areas are reserved for shipping.');
        $restriction2 = clone $restriction;
        $layer = $this->em->getRepository(Layer::class)->find(1);
        $layer->addRestrictionStart($restriction);
        $layer->addRestrictionEnd($restriction);
        $layer->addRestrictionStart($restriction2);
        $this->em->persist($layer);
        $this->em->flush();
        self::assertSame($this->em->getRepository(Restriction::class)->find(1), $restriction);
        self::assertSame($this->em->getRepository(Restriction::class)->find(2), $restriction2);
    }

    public function testObjectiveEntity(): void
    {
        $this->start();
        $objective = new Objective();
        $objective->setObjectiveTitle('Renewable Energy Production - 2030');
        $objective->setObjectiveDescription(
            'Increase the current renewable energy production to 12.0 GW by 2030.'
        );
        $objective->setObjectiveDeadline(192);
        $objective->setObjectiveLastupdate(100);
        $country = $this->em->getRepository(Country::class)->find(1);
        $country->addObjective($objective);
        $this->em->persist($country);
        $this->em->flush();
        self::assertSame($this->em->getRepository(Objective::class)->find(1), $objective);
    }

    public function testMelLayerRelationship(): void
    {
        $this->start();
        $layer = $this->em->getRepository(Layer::class)->find(1);
        $layer->setLayerName('Pressure layer');

        $layer2 = new Layer();
        $layer2->setLayerName('First layer generating pressure');
        $layer2->setLayerGeoType(LayerGeoType::RASTER);
        $layer2->setLayerGroup('northsee');
        $layer2->setLayerEditable(0);

        $layer3 = new Layer();
        $layer3->setLayerName('Second layer generating pressure');
        $layer3->setLayerGeoType(LayerGeoType::RASTER);
        $layer3->setLayerGroup('northsee');
        $layer3->setLayerEditable(0);

        $layer->addPressureGeneratingLayer($layer2);
        $layer->addPressureGeneratingLayer($layer3);

        $this->em->persist($layer);
        $this->em->flush();

        self::assertSame($layer->getPressureGeneratingLayer()[0], $layer2);
    }

    public function testPlan(): void
    {
        $this->start();
        $plan = new Plan();
        $plan->setPlanName('test plan');
        $plan->setPlanDescription('this is a test plan');
        $plan->setCountry($this->em->getRepository(Country::class)->find(1));
        $plan->setPlanGametime(5);
        $plan->setPlanState(PlanState::APPROVED);
        $this->em->persist($plan);

        $layerMetaData = $this->emServerManager->getRepository(GameConfigVersion::class)->find(1);
        $planFromConfig = $layerMetaData->getGameConfigComplete()['datamodel']['plans'][0];
        $plan2 = $this->normalizer->denormalize($planFromConfig, Plan::class);
        $plan2->setPlanDescription('test description');
        $plan2->setCountry($this->em->getRepository(Country::class)->find($planFromConfig['plan_country_id']));
        $plan2->setPlanState(PlanState::APPROVED);
        $derivedLayer = new Layer();
        $derivedLayer->setOriginalLayer($this->em->getRepository(Layer::class)->find(1));
        $geometry = new Geometry();
        $geometry->setGeometryGeometry(
            '[[1800176.69845479,748903.878],[4800176.69845479,2483199.127],[7398173.756,2483199.127]]'
        ); // coordinates following a certain projection mode
        $geometry->setGeometryData(
            '{"minx":1800176.698454788,"miny":748903.878,"maxx":7398173.756,"maxy":2483199.127}'
        ); // json representation of feature properties
        $geometry->setCountry(
            $this->em->getRepository(Country::class)->find(1)
        );
        $geometry->setGeometryType('0');
        $geometry->setGeometryMspid('z4fba98446ce9d9ff');
        $derivedLayer->addGeometry($geometry);
        $planLayer = new PlanLayer();
        $planLayer->setLayer($derivedLayer);
        $plan2->addPlanLayer($planLayer);

        $planDelete = new PlanDelete();
        $planDelete->setGeometry($this->em->getRepository(Geometry::class)->find(1));
        $planDelete->setLayer($this->em->getRepository(Layer::class)->find(1));
        $plan2->addPlanDelete($planDelete);

        $planMessage = new PlanMessage();
        $planMessage->setCountry($plan2->getCountry());
        $planMessage->setPlanMessageUserName('test user');
        $planMessage->setPlanMessageText('test text hello hello');
        $plan2->addPlanMessage($planMessage);

        $planRestrictionArea = new PlanRestrictionArea();
        $planRestrictionArea->setLayer($this->em->getRepository(Layer::class)->find(1));
        $planRestrictionArea->setCountry($this->em->getRepository(Country::class)->find(1));
        $planRestrictionArea->setPlanRestrictionAreaEntityType(10);
        $planRestrictionArea->setPlanRestrictionAreaSize(0);
        $plan2->addPlanRestrictionArea($planRestrictionArea);

        $this->em->persist($plan2);
        $this->em->flush();

        self::assertSame($this->em->getRepository(Plan::class)->find(1), $plan);
        self::assertSame($plan2, $planLayer->getPlan());
        self::assertSame($plan2, $planDelete->getPlan());
//        self::assertSame($plan2->getPlanMessage()[0], $planMessage);
        self::assertSame($plan2->getPlanRestrictionArea()[0], $planRestrictionArea);
    }

    public function testFishing(): void
    {
        $this->start();
        $fishing = new Fishing();
        $country = $this->em->getRepository(Country::class)->find(1);
        $fishing->setCountry($country);
        $fishing->setFishingType('testing');
        $plan = $this->em->getRepository(Plan::class)->find(1);
        $plan->addFishing($fishing);
        $this->em->persist($plan);
        $this->em->flush();

        self::assertSame($fishing->getPlan(), $plan);
        self::assertSame($fishing->getCountry(), $country);
    }

    public function testGrid(): void
    {
        $this->start();
        $geometry = $this->em->getRepository(Geometry::class)->find(1);
        $geometry2 = $this->em->getRepository(Geometry::class)->find(2);
        $grid = new Grid();
        $grid->setGridName('test grid');
        $grid->setPlan($this->em->getRepository(Plan::class)->find(1));
        $grid->addSourceGeometry($geometry);
        $grid->addSocketGeometry($geometry2);

        $grid2 = new Grid();
        $grid2->setGridName('grid derived from test grid');
        $grid2->setPlan($this->em->getRepository(Plan::class)->find(1));

        $grid->addDerivedGrid($grid2);
        $this->em->persist($grid);
        $this->em->flush();

        self::assertSame(
            $this->em->getRepository(Grid::class)->find(1)->getDerivedGrid()[0],
            $grid2
        );
        self::assertSame($geometry->getSourceForGrid()[0], $grid);
        self::assertSame($geometry2->getSocketForGrid()[0], $grid);

        $gridEnergy = new GridEnergy();
        $gridEnergy->setGrid($grid);

        $country = $this->em->getRepository(Country::class)->find(1);
        $country->addGridEnergy($gridEnergy);
        $this->em->persist($country);
        $this->em->flush();

        self::assertSame(
            $this->em->getRepository(Country::class)->find(1)->getGridEnergy()[0],
            $gridEnergy
        );

        $plan2 = $this->em->getRepository(Plan::class)->find(2);
        $plan2->addGridToRemove($grid2);
        $this->em->persist($plan2);
        $this->em->flush();

        self::assertSame($grid2->getPlanToRemove()[0], $plan2);
    }

    private function start(): void
    {
        $container = static::getContainer();
        $test = self::DBNAME;
        $this->em = $container->get("doctrine.orm.{$test}_entity_manager");
        $this->emServerManager = $container->get("doctrine.orm.msp_server_manager_entity_manager");
        $this->validator = $container->get("validator");
        $this->normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
    }

    public static function setUpBeforeClass(): void
    {
        GameListCreationTest::setUpBeforeClass();
        // completely removes, creates and migrates the test database

        $app = new Application(static::bootKernel());
        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--connection' => self::DBNAME,
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());

        $input2 = new ArrayInput([
            'command' => 'doctrine:database:create',
            '--connection' => self::DBNAME
        ]);
        $input2->setInteractive(false);
        $app->doRun($input2, new NullOutput());

        $input3 = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--em' => self::DBNAME,
        ]);
        $input3->setInteractive(false);
        $app->doRun($input3, new NullOutput());
    }
}
