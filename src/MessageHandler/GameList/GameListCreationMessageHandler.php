<?php

namespace App\MessageHandler\GameList;

use App\Controller\SessionAPI\SELController;
use App\Entity\Country;
use App\Entity\EnergyConnection;
use App\Entity\EnergyOutput;
use App\Entity\Fishing;
use App\Entity\Game;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Communicator\GeoServerCommunicator;
use App\Domain\Services\ConnectionManager;
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
use App\Entity\ServerManager\GameList;
use App\Logger\GameSessionLogger;
use App\Message\GameList\GameListCreationMessage;
use App\Repository\ServerManager\GameListRepository;
use App\VersionsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Jfcherng\Diff\DiffHelper;
use JsonSchema\Validator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
//use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use function App\await;

//#[AsMessageHandler]
class GameListCreationMessageHandler
{
    private string $database;
    private EntityManagerInterface $entityManager;
    private GameList $gameSession;
    private array $dataModel;
    private ObjectNormalizer $normalizer;

    private array $oldToNewGeometryIDs;

    public function __construct(
        private readonly EntityManagerInterface $mspServerManagerEntityManager,
        private readonly GameListRepository $gameListRepository,
        private readonly LoggerInterface $gameSessionLogger,
        private readonly GameSessionLogger $gameSessionLogFileHandling,
        private readonly HttpClientInterface $client,
        private readonly KernelInterface $kernel,
        private readonly ContainerBagInterface $params,
        private readonly VersionsProvider $provider
    ) {
        $this->normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
    }

    /**
     * @throws \Exception
     */
    public function __invoke(GameListCreationMessage $gameList): void
    {
        $this->gameSession = $this->gameListRepository->find($gameList->id)
            ?? throw new \Exception('Game session not found, so cannot continue.');
        $connectionManager = ConnectionManager::getInstance();
        $this->database = $connectionManager->getGameSessionDbName($this->gameSession->getId());
        $this->entityManager = $this->kernel->getContainer()->get("doctrine.orm.{$this->database}_entity_manager");
        try {
            $this->gameSessionLogFileHandling->empty($this->gameSession->getId());
            $this->validateGameConfigComplete();
            $this->gameSessionLogger->notice(
                'Session {name} creation initiated. This might take a while.',
                ['name' => $this->gameSession->getName(), 'gameSession' => $this->gameSession->getId()]
            );
            $this->dropSessionDatabase();
            $this->createSessionDatabase();
            $this->migrateSessionDatabase();
            $this->resetSessionRasterStore();
            $this->createSessionRunningConfig();
            $this->finaliseSession();
            $this->gameSessionLogger->notice(
                'Session {name} created and ready for use.',
                ['name' => $this->gameSession->getName(), 'gameSession' => $this->gameSession->getId()]
            );
            $state = 'healthy';
        } catch (\Throwable $e) {
            $this->gameSessionLogger->error(
                'Session {name} failed to create. Try to resolve the problem and retry. Problem: {problem}',
                [
                   'name' => $this->gameSession->getName(),
                   'problem' => $e->getMessage().' '.$e->getTraceAsString(),
                   'gameSession' => $this->gameSession->getId()
                ]
            );
            $state = 'failed';
        }
        $this->gameSession->setSessionState(new GameSessionStateValue($state));
        $this->mspServerManagerEntityManager->persist($this->gameSession);
        $this->mspServerManagerEntityManager->flush();
    }

    private function dropSessionDatabase(): void
    {
        $app = new Application($this->kernel);
        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--force' => true,
            '--connection' => $this->database,
            '--env' => $_ENV['APP_ENV']
        ]);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $app->doRun($input, $output);
        $this->gameSessionLogger->info(
            (string) $input.' resulted in: '.$output->fetch(),
            ['gameSession' => $this->gameSession->getId()]
        );
        sleep(1); // todo: to confirm this is necessary
    }

    private function createSessionDatabase(): void
    {
        $app = new Application($this->kernel);
        $input = new ArrayInput([
            'command' => 'doctrine:database:create',
            '--connection' => $this->database,
            '--env' => $_ENV['APP_ENV']
        ]);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $app->doRun($input, $output);
        $this->gameSessionLogger->info(
            (string) $input.' resulted in: '.$output->fetch(),
            ['gameSession' => $this->gameSession->getId()]
        );
        sleep(1); // todo: to confirm this is necessary
    }

    private function migrateSessionDatabase(): void
    {
        $app = new Application($this->kernel);
        $input = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--em' => $this->database,
            '--env' => $_ENV['APP_ENV']
        ]);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $app->doRun($input, $output);
        $this->gameSessionLogger->info(
            (string) $input.' resulted in: '.$output->fetch(),
            ['gameSession' => $this->gameSession->getId()]
        );
        sleep(1); // todo: to confirm this is necessary
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function resetSessionRasterStore(): void
    {
        $sessionRasterStore = $_ENV['APP_ENV'] == 'test' ?
            $this->params->get('app.session_raster_dir_test') :
            $this->params->get('app.session_raster_dir');
        $sessionRasterStore .= $this->gameSession->getId();
        $fileSystem = new Filesystem();
        if ($fileSystem->exists($sessionRasterStore)) {
            $fileSystem->remove($sessionRasterStore);
        }
        $fileSystem->mkdir($sessionRasterStore);
        $fileSystem->mkdir($sessionRasterStore . '/archive');
        $this->gameSessionLogger->info(
            'Reset the session raster store at {sessionRasterStore}',
            ['gameSession' => $this->gameSession->getId(), 'sessionRasterStore' => $sessionRasterStore]
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function createSessionRunningConfig(): void
    {
        $sessionConfigStore = $_ENV['APP_ENV'] == 'test' ?
            $this->params->get('app.session_config_dir_test') :
            $this->params->get('app.session_config_dir');
        $sessionConfigStore .= sprintf($this->params->get('app.session_config_name'), $this->gameSession->getId());
        $fileSystem = new Filesystem();
        $fileSystem->copy(
            $this->params->get('app.server_manager_config_dir').
            $this->gameSession->getGameConfigVersion()->getFilePath(),
            $sessionConfigStore,
            true
        );
        $this->gameSessionLogger->info(
            'Created the running session config file at {sessionConfigStore}',
            ['gameSession' => $this->gameSession->getId(), 'sessionConfigStore' => $sessionConfigStore]
        );
    }

    /**
     * @throws \Exception
     * @throws ExceptionInterface
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function finaliseSession(): void
    {
        $this->setupGame();
        $this->setupGameCountries();
        $this->importLayerData();
        $this->setupRestrictions();
        $this->setupSimulations();
        $this->setupObjectives();
        $this->setupPlans();
        $this->setupGameWatchdogAndAccess();
        $this->entityManager->flush();
    }

    /**
     * @throws \Exception
     */
    private function setupGame(): void
    {
        $game = new Game();
        $game->setGameId(1);
        $game->setGameStart($this->dataModel['start']);
        $game->setGamePlanningGametime($this->dataModel['era_planning_months']);
        $game->setGamePlanningRealtime($this->dataModel['era_planning_realtime']);
        $game->setGamePlanningEraRealtimeComplete();
        $game->setGameEratime($this->dataModel['era_total_months']);
        $this->entityManager->persist($game);
        $this->gameSessionLogger->info(
            'Basic game parameters set up.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    /**
     * @throws \Exception
     */
    private function setupGameCountries(): void
    {
        $countries[] = (new Country())
            ->setCountryId(1)
            ->setCountryColour($this->dataModel['user_admin_color'])
            ->setCountryIsManager(1);
        $countries[] = (new Country())
            ->setCountryId(1)
            ->setCountryColour($this->dataModel['user_region_manager_color'])
            ->setCountryIsManager(1);
        foreach ($this->dataModel['meta'] as $layerMeta) {
            if ($layerMeta['layer_name'] == $this->dataModel['countries']) {
                foreach ($layerMeta['layer_type'] as $country) {
                    $countries[] = (new Country())
                        ->setCountryId($country['value'])
                        ->setCountryName($country['displayName'])
                        ->setCountryColour($country['polygonColor'])
                        ->setCountryIsManager(0);
                }
                break;
            }
        }
        foreach ($countries as $country) {
            $this->entityManager->persist($country);
        }
        $this->gameSessionLogger->info(
            'All countries set up.',
            ['gameSession' => $this->gameSession->getId()]
        );
        $this->entityManager->flush(); //because we will look up countries later on
    }

    /**
     * @throws \Exception
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function importLayerData(): void
    {
        $geoServerCommunicator = new GeoServerCommunicator($this->client);
        $geoServerCommunicator->setBaseURL($this->gameSession->getGameGeoServer()->getAddress());
        $geoServerCommunicator->setUsername($this->gameSession->getGameGeoServer()->getUsername());
        $geoServerCommunicator->setPassword($this->gameSession->getGameGeoServer()->getPassword());

        foreach ($this->dataModel['meta'] as $layerMetaData) {
            $layer = $this->normalizer->denormalize($layerMetaData, Layer::class);
            $layer->setContextCreatingGameSession($this->gameSession->getId()); // indicator for SessionEntityListener
            $this->gameSessionLogger->info(
                'Starting import of layer {layerName}...',
                ['gameSession' => $this->gameSession->getId(), 'layerName' => $layer->getLayerName()]
            );
            if ($layer->getLayerGeotype() == "raster") {
                $this->importLayerRasterData(
                    $layer,
                    $geoServerCommunicator
                );
            } else {
                $this->importLayerGeometryData(
                    $layer,
                    $geoServerCommunicator
                );
            }
            $this->gameSessionLogger->info(
                'Finished importing layer {layerName}.',
                [
                    'gameSession' => $this->gameSession->getId(),
                    'layerName' => $layer->getLayerName()
                ]
            );
            $this->importLayerTypeAvailabilityRestrictions($layer);
            $this->entityManager->persist($layer);
            $this->entityManager->flush();
            $this->checkForDuplicateMspids($layer); //flush required, hence here
        }
    }

    private function importLayerTypeAvailabilityRestrictions(Layer $layer): void
    {
        $counter = 0;
        foreach ($layer->getLayerType() as $typeId => $typeMeta) {
            if (isset($typeMeta["availability"]) && (int) $typeMeta["availability"] > 0) {
                $counter++;
                $restriction = new Restriction();
                $restriction->setRestrictionStartLayerType($typeId);
                $restriction->setRestrictionEndLayerType($typeId);
                $restriction->setRestrictionSort('TYPE_UNAVAILABLE');
                $restriction->setRestrictionType('ERROR');
                $restriction->setRestrictionMessage(
                    'Type not yet available at the plan implementation time.'
                );
                $layer->addRestrictionStart($restriction);
                $layer->addRestrictionEnd($restriction);
            }
        }
        if ($counter > 0) {
            $this->gameSessionLogger->info(
                'Added {counter} availability restrictions for layer {layerName}.',
                [
                    'gameSession' => $this->gameSession->getId(),
                    'layerName' => $layer->getLayerName(),
                    'counter' => $counter
                ]
            );
        }
    }

    /**
     * @throws \Exception
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function importLayerRasterData(
        Layer $layer,
        GeoServerCommunicator $geoServerCommunicator
    ): void {
        $rasterPath = ($_ENV['APP_ENV'] == 'test' ?
            $this->params->get('app.session_raster_dir_test') :
            $this->params->get('app.session_raster_dir'))
            . "{$this->gameSession->getId()}/{$layer->getLayerName()}.png";
        if ($layer->getLayerDownloadFromGeoserver()) {
            $this->gameSessionLogger->debug(
                'Calling GeoServer to obtain raster metadata.',
                ['gameSession' => $this->gameSession->getId()]
            );
            $rasterMetaData = $geoServerCommunicator->getRasterMetaData(
                $this->dataModel['region'],
                $layer->getLayerName()
            );
            $this->gameSessionLogger->debug(
                "Call to GeoServer completed: {geoserverURL}",
                [
                    'gameSession' => $this->gameSession->getId(),
                    'geoserverURL' => $geoServerCommunicator->getLastCompleteURLCalled()
                ]
            );
            $this->gameSessionLogger->debug(
                'Calling GeoServer to obtain actual raster data.',
                ['gameSession' => $this->gameSession->getId()]
            );
            $rasterData = $geoServerCommunicator->getRasterDataThroughMetaData(
                $this->dataModel['region'],
                $layer,
                $rasterMetaData
            );
            $this->gameSessionLogger->debug(
                "Call to GeoServer completed: {geoserverURL}",
                [
                    'gameSession' => $this->gameSession->getId(),
                    'geoserverURL' => $geoServerCommunicator->getLastCompleteURLCalled()
                ]
            );
            $fileSystem = new Filesystem();
            $fileSystem->dumpFile(
                $rasterPath,
                $rasterData
            );
            $message = 'Successfully retrieved {layerName} and stored the raster file at {rasterPath}.';
        }
        $layer->setLayerRaster($rasterMetaData ?? null);
        $this->gameSessionLogger->info(
            $message ?? 'Successfully retrieved {layerName} without storing a raster file, as requested.',
            [
                'gameSession' => $this->gameSession->getId(),
                'layerName' => $layer->getLayerName(),
                'rasterPath' => $rasterPath
            ]
        );
    }

    /**
     * @throws \Exception
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function importLayerGeometryData(
        Layer $layer,
        GeoServerCommunicator $geoServerCommunicator
    ): void {
        if ($layer->getLayerDownloadFromGeoserver()) {
            $this->gameSessionLogger->debug(
                'Calling GeoServer to obtain layer description.',
                ['gameSession' => $this->gameSession->getId()]
            );
            $layersContainer = $geoServerCommunicator->getLayerDescription(
                $this->dataModel['region'],
                $layer->getLayerName()
            );
            $this->gameSessionLogger->debug(
                "Call to GeoServer completed: {geoserverURL}",
                [
                    'gameSession' => $this->gameSession->getId(),
                    'geoserverURL' => $geoServerCommunicator->getLastCompleteURLCalled()
                ]
            );
            foreach ($layersContainer as $layerWithin) {
                $this->gameSessionLogger->debug(
                    'Calling GeoServer to obtain layer geometry features.',
                    ['gameSession' => $this->gameSession->getId()]
                );
                $geoserverReturn = $geoServerCommunicator->getLayerGeometryFeatures($layerWithin['layerName']);
                $this->gameSessionLogger->debug(
                    "Call to GeoServer completed: {geoserverURL}",
                    [
                        'gameSession' => $this->gameSession->getId(),
                        'geoserverURL' => $geoServerCommunicator->getLastCompleteURLCalled()
                    ]
                );
                $features = $geoserverReturn['features']
                    ?? throw new \Exception(
                        'Geometry data call did not return a features variable, so something must be wrong.'
                    );
                $numFeatures = count($features);
                $this->gameSessionLogger->debug(
                    "Starting import of all {$numFeatures} layer geometry features.",
                    ['gameSession' => $this->gameSession->getId()]
                );
                foreach ($features as $feature) {
                    $geometryTypeAdded = $this->addLayerGeometryFromFeatureDataSet($layer, $feature);
                    isset($counter[$geometryTypeAdded]) ?
                        $counter[$geometryTypeAdded]++ :
                        $counter[$geometryTypeAdded] = 1;
                }
                $this->gameSessionLogger->debug(
                    "Import of layer geometry features completed: {geometryTypeDetails}.",
                    [
                        'gameSession' => $this->gameSession->getId(),
                        'geometryTypeDetails' => http_build_query($counter ?? '', '', ' ')
                    ]
                );
            }
            $message = 'Successfully retrieved {layerName} and stored the geometry in the database.';
            if ($layer->hasGeometryWithGeneratedMspids()) {
                $message .= ' Note that at least some of the geometry has auto-generated MSP IDs.';
            }
        }
        $this->gameSessionLogger->info(
            $message ?? 'Successfully retrieved {layerName} without storing geometry in the database, as requested.',
            ['gameSession' => $this->gameSession->getId(), 'layerName' => $layer->getLayerName()]
        );
    }

    /**
     * @throws \Exception
     */
    private function addLayerGeometryFromFeatureDataSet(Layer $layer, array $feature): string
    {
        $geometryData = $feature['geometry'] ?? throw new \Exception(
            'No geometry within returned features variable, so something must be wrong.'
        );
        self::ensureMultiData($geometryData);
        $feature['properties']['country_object'] = (!empty($feature['properties']['country_id'])) ?
            $this->entityManager->getRepository(Country::class)->find($feature['properties']['country_id']) :
            null;
        if (strcasecmp($geometryData['type'], 'MultiPolygon') == 0) {
            foreach ($geometryData['coordinates'] as $multiPolygon) {
                if (!is_array($multiPolygon)) {
                    continue;
                }
                $geometryToSubtractFrom = null;
                foreach ($multiPolygon as $key => $polygon) {
                    $geometry = new Geometry($layer);
                    $geometry->setGeometryGeometry($polygon);
                    $geometry->setGeometryPropertiesThroughFeature($feature['properties']);
                    $geometry->setGeometryToSubtractFrom($geometryToSubtractFrom);
                    $layer->addGeometry($geometry);
                    if (sizeof($multiPolygon) > 1 && $key == 0) {
                        $geometryToSubtractFrom = $geometry;
                    }
                }
            }
        } elseif (strcasecmp($geometryData['type'], 'MultiLineString') == 0) {
            foreach ($geometryData['coordinates'] as $line) {
                $geometry = new Geometry($layer);
                $geometry->setGeometryGeometry($line);
                $geometry->setGeometryPropertiesThroughFeature($feature['properties']);
                $layer->addGeometry($geometry);
            }
        } elseif (strcasecmp($geometryData['type'], 'MultiPoint') == 0) {
            $geometry = new Geometry($layer);
            $geometry->setGeometryGeometry($geometryData["coordinates"]);
            $geometry->setGeometryPropertiesThroughFeature($feature['properties']);
            $layer->addGeometry($geometry);
        }
        return $geometryData['type'];
    }

    public function checkForDuplicateMspids(Layer $layer): void
    {
        if ($layer->getLayerGeotype() == 'raster') {
            return;
        }
        $list = $this->entityManager->getRepository(Geometry::class)->findDuplicateMspids($layer->getLayerId());
        if (empty($list)) {
            $this->gameSessionLogger->info(
                'Check for duplicate MSP IDs among {layerName}\'s geometry complete. None found, yay!',
                ['gameSession' => $this->gameSession->getId(), 'layerName' => $layer->getLayerName()]
            );
            return;
        }
        $message = 'Check for duplicate MSP IDs in {layerName] returned {counted} geometry records with duplicates.'
            .PHP_EOL;
        $previousMspId = null;
        $previousGeometryData = null;
        foreach ($list as $key => $item) {
            $item['geometryData'] .= PHP_EOL; //just a little hack to get rid of stupid 'no line ending' notice
            if ($item['geometryMspid'] != $previousMspId) {
                $geometryDataBit = substr($item['geometryData'], 0, 100).'...';
                $message .= "MSP ID {$item['geometryMspid']} was used for a feature with properties {$geometryDataBit} "
                    .PHP_EOL;
                $previousGeometryData = $item['geometryData'];
            } else {
                $diff = DiffHelper::calculate($previousGeometryData, $item['geometryData']);
                if (!empty($diff)) {
                    $message .= " and for a feature with differing properties {$diff}".PHP_EOL;
                } else {
                    $message .= " and for another feature but seemingly with the same properties.".PHP_EOL;
                }
            }
            if ($key == 50) {
                $message .= "Now terminating the listing of duplicated geometry to not clog up this log.".PHP_EOL;
                break;
            }
            $previousMspId = $item['geometryMspid'];
        }
        $this->gameSessionLogger->error(
            $message,
            [
                'gameSession' => $this->gameSession->getId(),
                'layerName' => $layer->getLayerName(),
                'counted' => count($list)
            ]
        );
    }

    public static function ensureMultiData(&$geometry): void
    {
        if ($geometry['type'] == 'Polygon' || $geometry['type'] == 'LineString' ||  $geometry['type'] == 'Point') {
            $geometry['coordinates'] = [$geometry['coordinates']];
            $geometry['type'] = 'Multi'.$geometry['type'];
        }
    }

    /**
     * @throws \Exception
     */
    private function setupRestrictions(): void
    {
        if (empty($this->dataModel['restrictions'])) {
            $this->gameSessionLogger->info(
                'No layer restrictions to set up.',
                ['gameSession' => $this->gameSession->getId()]
            );
            return;
        }
        $this->gameSessionLogger->info(
            'Found {count} restriction definitions, commencing setup.',
            ['gameSession' => $this->gameSession->getId(), 'count' => count($this->dataModel['restrictions'])]
        );
        foreach ($this->dataModel['restrictions'] as $restrictionKey => $restrictionConfig) {
            foreach ($restrictionConfig as $restrictionItem) {
                $restriction = new Restriction();
                $startLayer = $this->entityManager
                    ->getRepository(Layer::class)->findOneBy(['layerName' => $restrictionItem['startlayer']]);
                if (empty($startLayer)) {
                    $this->gameSessionLogger->warning(
                        "Start layer {startLayer} used in restriction {restrictionKey} does not seem to exist.".
                        "Are you sure this layer has been added under the 'meta' object? Restriction skipped.",
                        [
                            'gameSession' => $this->gameSession->getId(),
                            'restrictionKey' => $restrictionKey + 1,
                            'startLayer' => $restrictionItem['startlayer']
                        ]
                    );
                    continue;
                }
                $endLayer = $this->entityManager
                    ->getRepository(Layer::class)->findOneBy(['layerName' => $restrictionItem['endlayer']]);
                if (empty($endLayer)) {
                    $this->gameSessionLogger->warning(
                        "End layer {endLayer} used in restriction {restrictionKey} does not seem to exist. ".
                        "Are you sure this layer has been added under the 'meta' object? Restriction skipped.",
                        [
                            'gameSession' => $this->gameSession->getId(),
                            'restrictionKey' => $restrictionKey + 1,
                            'endLayer' => $restrictionItem['endlayer']
                        ]
                    );
                    continue;
                }
                $restriction->setRestrictionStartLayer($startLayer);
                $restriction->setRestrictionEndLayer($endLayer);
                $restriction->setRestrictionSort($restrictionItem['sort']);
                $restriction->setRestrictionValue($restrictionItem['value']);
                $restriction->setRestrictionType($restrictionItem['type']);
                $restriction->setRestrictionMessage($restrictionItem['message']);
                $restriction->setRestrictionStartLayerType($restrictionItem['starttype']);
                $restriction->setRestrictionEndLayerType($restrictionItem['endtype']);
                $this->entityManager->persist($restriction);
            }
        }
        $this->entityManager->flush();
        $this->gameSessionLogger->info(
            'Restrictions setup complete.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    private function setupSimulations(): void
    {
        $simulationsDone = [];
        $possibleSims = array_keys($this->provider->getComponentsVersions());
        foreach ($possibleSims as $possibleSim) {
            $simSessionCreationMethod = "{$possibleSim}SessionCreation";
            if (array_key_exists($possibleSim, $this->dataModel)
                && is_array($this->dataModel[$possibleSim])
                && method_exists($this, $simSessionCreationMethod)
            ) {
                $this->$simSessionCreationMethod();
                $simulationsDone[] = $possibleSim;
            }
        }
        $remainingSims = array_diff($possibleSims, $simulationsDone);
        foreach ($remainingSims as $remainingSim) {
            if (key_exists($remainingSim, $this->dataModel)) {
                $this->gameSessionLogger->error(
                    'Unable to set up {simulation}, as its SessionCreation function does not exist.',
                    ['simulation' => $remainingSim, 'gameSession' => $this->gameSession->getId()]
                );
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function MELSessionCreation(): void
    {
        $this->gameSessionLogger->info(
            'Setting up simulation MEL...',
            ['gameSession' => $this->gameSession->getId()]
        );
        $config = $this->dataModel['MEL'];
        if (isset($config["fishing"])) {
            $countries = $this->entityManager->getRepository(Country::class)->findBy(['countryIsManager' => 0]);
            foreach ($config["fishing"] as $fleet) {
                if (isset($fleet["initialFishingDistribution"])) {
                    foreach ($countries as $country) {
                        $foundCountry = false;
                        foreach ($fleet["initialFishingDistribution"] as $distribution) {
                            if ($distribution["country_id"] == $country->getCountryId()) {
                                $foundCountry = true;
                                break;
                            }
                        }
                        if (!$foundCountry) {
                            $this->gameSessionLogger->error(
                                "Country with ID {country} is missing a distribution entry in the ".
                                "initialFishingDistribution table for fleet {fleet} for MEL.",
                                [
                                    'country' => $country->getCountryId(),
                                    'fleet' => $fleet["name"],
                                    'gameSession' => $this->gameSession->getId()
                                ]
                            );
                        }
                    }
                }
            }
        }

        foreach ($config['pressures'] as $pressure) {
            $pressureLayer = $this->setupMELLayer($pressure['name']);
            foreach ($pressure['layers'] as $layerGeneratingPressures) {
                $layer = $this->entityManager->getRepository(Layer::class)
                    ->findOneBy(['layerName' => $layerGeneratingPressures['name']]);
                if (!empty($layer)) {
                    //add a layer to the mel_layer table for faster accessing
                    $pressureLayer->addPressureGeneratingLayer($layer);
                    $this->entityManager->persist($pressureLayer);
                }
            }
        }
        foreach ($config['outcomes'] as $outcome) {
            $this->setupMELLayer($outcome['name']);
        }
        $this->gameSessionLogger->info(
            'Finished setting up simulation MEL.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    /**
     * @throws \Exception
     */
    private function setupMELLayer(string $melLayerName): Layer
    {
        $layerName = "mel_" . str_replace(" ", "_", $melLayerName);
        $layer = $this->entityManager->getRepository(Layer::class)->findOneBy(['layerName' => $layerName]);
        if (empty($layer)) {
            throw new \Exception(
                "Pressure layer {$layerName} not found. Make sure it has been defined under 'meta'."
            );
        }
        $layerRaster = $layer->getLayerRaster(); //also sets all other raster metadata properties
        $layer->setLayerRasterURL("{$layerName}.tif");
        $layer->setLayerRasterBoundingbox([
            [
                $this->dataModel['MEl']['x_min'],
                $this->dataModel['MEl']["y_min"]
            ],
            [
                $this->dataModel['MEl']["x_max"],
                $this->dataModel['MEl']["y_max"]
            ]
        ]);
        $layer->setLayerRaster($layerRaster); //also sets by getting other raster metadata properties
        $this->entityManager->persist($layer);
        return $layer;
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws \Exception
     */
    private function SELSessionCreation(): void
    {
        $this->gameSessionLogger->info(
            'Setting up simulation SEL...',
            ['gameSession' => $this->gameSession->getId()]
        );
        $boundsConfig = SELController::calculateAlignedSimulationBounds(
            $this->dataModel,
            $this->entityManager
        );
        foreach ($this->dataModel["SEL"]["heatmap_settings"] as $heatmap) {
            $selOutputLayer = $this->entityManager->getRepository(Layer::class)->findOneBy(
                ['layerName' => $heatmap['layer_name']]
            );
            if (is_null($selOutputLayer)) {
                throw new \Exception(
                    'The layer '.$heatmap['layer_name'].' referenced in the heatmap settings has not been 
                    found in the database, so cannot continue. Are you sure it has been defined separately as an 
                    actual layer in the configuration file?'
                );
            }
            $layerRaster = $selOutputLayer->getLayerRaster(); //also sets all other raster metadata properties
            $selOutputLayer->setLayerRasterURL("{$selOutputLayer->getLayerName()}.png");
            if (isset($heatmap["output_for_mel"]) && $heatmap["output_for_mel"] === true) {
                if (empty($this->dataModel["MEL"])) {
                    throw new \Exception("SEL has a layer {$heatmap["layer_name"]} that is marked ".
                        "for use by MEL. However the MEL configuration is not found in the current config file.");
                }
                if (!array_key_exists("x_min", $this->dataModel["MEL"])
                    || !array_key_exists("y_min", $this->dataModel["MEL"])
                    || !array_key_exists("x_max", $this->dataModel["MEL"])
                    || !array_key_exists("y_max", $this->dataModel["MEL"])
                ) {
                    throw new \Exception("SEL has layer {$heatmap["layer_name"]} that is marked ".
                        "for use by MEL. However the bounding box configuration in the MEL section is incomplete.");
                }
                $selOutputLayer->setLayerRasterBoundingbox([
                    [$this->dataModel["MEL"]['x_min'], $this->dataModel["MEL"]['y_min']],
                    [$this->dataModel["MEL"]['x_max'], $this->dataModel["MEL"]['y_max']]
                ]);
            } else {
                $selOutputLayer->setLayerRasterBoundingbox([
                    [$boundsConfig['x_min'], $boundsConfig['y_min']],
                    [$boundsConfig['x_max'], $boundsConfig['y_max']]
                ]);
            }
            $selOutputLayer->setLayerRaster($layerRaster); //also sets by getting other raster metadata properties
            $this->entityManager->persist($selOutputLayer);
        }
        $this->gameSessionLogger->info(
            'Finished setting up simulation SEL.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    private function CELSessionCreation(): void
    {
        $this->gameSessionLogger->info(
            'For CEL no setup is required at all. Ready to go.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    /**
     * @throws \Exception
     * @throws ExceptionInterface
     */
    private function setupPlans(): void
    {
        if (empty($this->dataModel['plans'])) {
            $this->gameSessionLogger->info(
                'No plans defined, so nothing to import there.',
                ['gameSession' => $this->gameSession->getId()]
            );
        }
        $this->gameSessionLogger->info(
            'Starting to import all plans.',
            ['gameSession' => $this->gameSession->getId()]
        );
        foreach ($this->dataModel['plans'] as $planConfig) {
            $plan = $this->normalizer->denormalize($planConfig, Plan::class);
            $plan->setCountry(
                $this->entityManager->getRepository(Country::class)->find($planConfig['plan_country_id'])
            );
            $plan->setPlanState('APPROVED');
            $planCableConnections = [];
            $planEnergyOutput = [];
            foreach ($planConfig['fishing'] as $fishingConfig) {
                $fishing = $this->normalizer->denormalize($fishingConfig, Fishing::class);
                $fishing->setCountry(
                    $this->entityManager->getRepository(Country::class)->find($fishingConfig['fishing_country_id'])
                );
                $plan->addFishing($fishing);
            }

            foreach ($planConfig['messages'] as $planMessageConfig) {
                $planMessage = new PlanMessage();
                $planMessage->setCountry(
                    $this->entityManager->getRepository(Country::class)->find($planMessageConfig['country_id'])
                );
                $planMessage->setPlanMessageUserName($planMessageConfig['user_name']);
                $planMessage->setPlanMessageText($planMessageConfig['text']);
                $planMessage->setPlanMessageTime($planMessageConfig['time']);
                $plan->addPlanMessage($planMessage);
            }

            foreach ($planConfig['restriction_settings'] as $restrictionAreaConfig) {
                $planRestrictionArea = new PlanRestrictionArea();
                $planRestrictionArea->setLayer(
                    $this->entityManager->getRepository(Layer::class)->findOneBy(
                        ['layerName' => $restrictionAreaConfig['layer_name']]
                    )
                );
                $planRestrictionArea->setCountry(
                    $this->entityManager->getRepository(Country::class)->find($restrictionAreaConfig['country_id'])
                );
                $planRestrictionArea->setPlanRestrictionAreaEntityType($restrictionAreaConfig['entity_type_id']);
                $planRestrictionArea->setPlanRestrictionAreaSize($restrictionAreaConfig['size']);
                $plan->addPlanRestrictionArea($planRestrictionArea);
            }

            foreach ($planConfig['layers'] as $layerConfig) {
                $derivedLayer = new Layer();
                $derivedLayer->setOriginalLayer(
                    $this->entityManager->getRepository(Layer::class)->findOneBy(
                        ['layerName' => $layerConfig['name']]
                    )
                );
                foreach ($layerConfig['geometry'] as $layerGeometryConfig) {
                    $geometry = new Geometry();
                    $geometry->setOldGeometryId($layerGeometryConfig['geometry_id']);
                    $geometry->setGeometryData($layerGeometryConfig['data'] ?? null);
                    $geometry->setGeometryFID($layerGeometryConfig['FID']);
                    $geometry->setGeometryGeometry($layerGeometryConfig['geometry']);
                    if (!is_null($layerGeometryConfig['country'])) {
                        $geometry->setCountry($this->entityManager->getRepository(Country::class)->find(
                            $layerGeometryConfig['country']
                        ));
                    }
                    $geometry->setGeometryType($layerGeometryConfig['type']);
                    $geometry->setOriginalGeometry(
                        $this->findNewPersistentGeometry($layerGeometryConfig['base_geometry_info'], $geometry)
                    );
                    $derivedLayer->addGeometry($geometry);
                    $this->entityManager->persist($derivedLayer);
                    if (!empty($layerGeometryConfig['cable'])) {
                        $planCableConnections[] = array_merge(
                            $layerGeometryConfig['cable'],
                            $layerGeometryConfig['base_geometry_info']
                        );
                    }
                    if (!empty($layerGeometryConfig['energy_output'])) {
                        $planEnergyOutput[] = array_merge(
                            $layerGeometryConfig['energy_output'],
                            $layerGeometryConfig['base_geometry_info']
                        );
                    }
                }
                $this->entityManager->flush(); // have to be able to map old > new geometry IDs
                foreach ($derivedLayer->getGeometry() as $geometry) {
                    $this->oldToNewGeometryIDs[$geometry->getOldGeometryId()] = $geometry->getGeometryId();
                }
                $planLayer = new PlanLayer();
                $planLayer->setLayer($derivedLayer);
                $plan->addPlanLayer($planLayer);
                foreach ($layerConfig['deleted'] as $layerGeometryDeletedConfig) {
                    $planDelete = new PlanDelete();
                    $planDelete->setLayer($derivedLayer);
                    $planDelete->setGeometry(
                        $this->findNewPersistentGeometry($layerGeometryDeletedConfig['base_geometry_info'])
                    );
                    $plan->addPlanDelete($planDelete);
                }
            }
            $this->entityManager->persist($plan);
            $this->entityManager->flush(); // need to have a plan ID for next steps
            $this->setupPlannedCableConnections($planCableConnections);
            $this->setupPlannedEnergyOutput($planEnergyOutput);
            $this->setupPlannedGrids($planConfig['grids'], $plan);

            $plan->updatePlanConstructionTime();
            $this->entityManager->persist($plan);
            $this->gameSessionLogger->info(
                'Finished importing plan {planName}.',
                ['planName' => $plan->getPlanName(), 'gameSession' => $this->gameSession->getId()]
            );
        }
        $this->entityManager->flush();

        // final step to avoid client complaints
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update('App:Geometry', 'g')
            ->set('g.originalGeometry', 'g.geometryId')
            ->where($qb->expr()->isNull('g.originalGeometry'))
            ->getQuery()
            ->execute();
    }

    /**
     * @throws \Exception
     */
    private function setupPlannedCableConnections(array $cablesConfig): void
    {
        //Import energy connections now we know all geometry is known by the importer.
        foreach ($cablesConfig as $cableConfig) {
            //Import energy connections
            $energyConnection = new EnergyConnection();
            $energyConnection->setCableGeometry($this->findNewGeometry($cableConfig));
            $energyConnection->setStartGeometry($this->findNewGeometry($cableConfig['start']));
            $energyConnection->setEndGeometry($this->findNewGeometry($cableConfig['end']));
            $energyConnection->setEnergyConnectionStartCoordinates($cableConfig['coordinates']);
            $energyConnection->setEnergyConnectionLastupdate(100);
            $this->entityManager->persist($energyConnection);
        }
    }

    /**
     * @throws \Exception
     */
    private function setupPlannedEnergyOutput(array $energyOutputsConfig): void
    {
        foreach ($energyOutputsConfig as $energyOutputConfig) {
            $energyOutput = new EnergyOutput();
            $energyOutput->setGeometry($this->findNewGeometry($energyOutputConfig));
            $energyOutput->setEnergyOutputMaxcapacity($energyOutputConfig[0]['maxcapacity']);
            $energyOutput->setEnergyOutputActive($energyOutputConfig[0]['active']);
            $this->entityManager->persist($energyOutput);
        }
    }

    /**
     * @throws \Exception
     */
    private function setupPlannedGrids(array $planGridsConfig, Plan $plan): void
    {
        $importedGridIds = [];
        foreach ($planGridsConfig as $gridConfig) {
            $grid = new Grid();
            $grid->setGridName($gridConfig['name']);
            $grid->setGridActive($gridConfig['active']);
            $grid->setGridLastupdate(100);
            $grid->setPlan($plan);
            $this->entityManager->persist($grid);
            $this->entityManager->flush();
            $gridPersistent = $grid->getGridId();
            if ($gridConfig['grid_persistent'] == $gridConfig['grid_id']) {
                $importedGridIds[$gridConfig['grid_persistent']] = $grid->getGridId();
            } else {
                if (isset($importedGridIds[$gridConfig['grid_persistent']])) {
                    $gridPersistent = $importedGridIds[$gridConfig['grid_persistent']];
                } else {
                    throw new \Exception("Found reference persistent Grid ID (". $grid['grid_persistent'].
                        ") which has not been imported by the plans importer (yet).");
                }
            }
            $grid->setOriginalGrid(
                $this->entityManager->getRepository(Grid::class)->find($gridPersistent)
            );
            foreach ($gridConfig['energy'] as $gridEnergyConfig) {
                $gridEnergy = new GridEnergy();
                $gridEnergy->setCountry(
                    $this->entityManager->getRepository(Country::class)->find($gridEnergyConfig['country'])
                );
                $gridEnergy->setGridEnergyExpected($gridEnergyConfig['expected']);
                $grid->addGridEnergy($gridEnergy);
            }
            foreach ($gridConfig['removed'] as $gridRemovedConfig) {
                if (empty($importedGridIds[$gridRemovedConfig['grid_persistent']])) {
                    throw new \Exception("Found plan to remove grid ({$gridRemovedConfig['grid_persistent']}".
                        ") but this has not been imported by the plans importer (yet).");
                }
                $plan->addGridToRemove($this->entityManager->getRepository(Grid::class)->find(
                    $importedGridIds[$gridRemovedConfig['grid_persistent']]
                ));
                $this->entityManager->persist($plan);
            }
            foreach ($gridConfig['sockets'] as $gridSocketConfig) {
                $grid->addSocketGeometry($this->findNewGeometry($gridSocketConfig['geometry']));
            }
            foreach ($gridConfig['sources'] as $gridSourceConfig) {
                $grid->addSourceGeometry($this->findNewGeometry($gridSourceConfig['geometry']));
            }
            $this->entityManager->persist($grid);
        }
    }

    /**
     * When importing the geometry included in the 'plans' part of the config file, we'll need to map the geometry IDs
     * in there to the IDs given to that same geometry as it was imported into the database earlier...
     * ... and we'll need to be able to map *references* to any original geometry in there to the original geometry
     * as it was imported into the database earlier. For the second purpose we have this function.
     * @throws \Exception
     */
    private function findNewPersistentGeometry(array $baseGeometryInfo, ?Geometry $geometry = null): ?Geometry
    {
        if (!empty($baseGeometryInfo['geometry_mspid'])) {
            return $this->entityManager->getRepository(Geometry::class)->findOneBy(
                ['geometryMspid' => $baseGeometryInfo['geometry_mspid']]
            ); // as MSP IDs are meant to always stay the same across any session and config file
        }
        if (array_key_exists($baseGeometryInfo["geometry_persistent"], $this->oldToNewGeometryIDs)) {
            return $this->entityManager->getRepository(Geometry::class)->find(
                $this->oldToNewGeometryIDs[$baseGeometryInfo["geometry_persistent"]]
            ); // this means that the original (persisten) geometry being refered to was already put in the database
        }
        if (!empty($baseGeometryInfo['geometry_id']) && !empty($baseGeometryInfo['geometry_persistent']) &&
            $baseGeometryInfo['geometry_id'] == $baseGeometryInfo['geometry_persistent']) {
            return $geometry; // the geometry in this plan is completely new anyway, so it *is* the original
        }
        throw new \Exception(
            "Failed to find newly imported persistent geometry. No MSP ID was available, geometry_persistent ".
            "{$baseGeometryInfo["geometry_persistent"]} wasn't imported earlier, and this isn't new geometry.".
            var_export($baseGeometryInfo, true).var_export($this->oldToNewGeometryIDs, true)
        );
    }

    /**
     * When importing the geometry included in the 'plans' part of the config file, we'll need to map the geometry IDs
     * in there to the IDs given to that same geometry as it was imported into the database earlier...
     * ... and we'll need to be able to map *references* to any original geometry in there to the original geometry
     * as it was imported into the database earlier. For the first purpose we have this function.
     * @throws \Exception
     */
    private function findNewGeometry(array $baseGeometryInfo): ?Geometry
    {
        if (array_key_exists($baseGeometryInfo["geometry_id"], $this->oldToNewGeometryIDs)) {
            return $this->entityManager->getRepository(Geometry::class)->find(
                $this->oldToNewGeometryIDs[$baseGeometryInfo["geometry_id"]]
            );
        }
        // If we can't find the geometry id in the ones that we already have imported, check if the geometry id
        //   matches the persistent id, and if so select it by the mspid since this should all be present then.
        if ($baseGeometryInfo["geometry_id"] == $baseGeometryInfo["geometry_persistent"]) {
            if (isset($baseGeometryInfo["geometry_mspid"])) {
                return $this->entityManager->getRepository(Geometry::class)->findOneBy(
                    ['geometryMspid' => $baseGeometryInfo['geometry_mspid']]
                );
            }
            throw new \Exception("Found geometry (".implode(", ", $baseGeometryInfo).
                " which has not been imported by the plans importer. The persistent id matches but MSP ID is not set.");
        }
        throw new \Exception("Found geometry ID (Fallback field \"geometry_id\": ". $baseGeometryInfo["geometry_id"].
            ") which hasn't been imported by the plans importer yet.");
    }

    private function setupObjectives(): void
    {
        if (empty($this->dataModel['objectives'])) {
            $this->gameSessionLogger->info(
                'No objectives to set up.',
                ['gameSession' => $this->gameSession->getId()]
            );
            return;
        }
        foreach ($this->dataModel['objectives'] as $key => $objectiveConfig) {
            $country = $this->entityManager->getRepository(Country::class)
                ->findOneBy(['countryId' => $objectiveConfig['country_id']]);
            if (empty($country)) {
                $this->gameSessionLogger->warning(
                    'Country {countryId} set in objective {key} does not seem to exist.',
                    [
                        'gameSession' => $this->gameSession->getId(),
                        'key' => $key,
                        'countryId' => $objectiveConfig['country_id']
                    ]
                );
                continue;
            }
            $objective = new Objective();
            $objective->setCountry($country);
            $objective->setObjectiveTitle($objectiveConfig['title']);
            $objective->setObjectiveDescription($objectiveConfig['description']);
            $objective->setObjectiveDeadline($objectiveConfig['deadline']);
            $objective->setObjectiveLastupdate(100);
            $this->entityManager->persist($objective);
        }
        $this->gameSessionLogger->info(
            'A total of {count} objectives were set up successfully.',
            ['gameSession' => $this->gameSession->getId(), 'count' => count($this->dataModel['objectives'])]
        );
    }

    /**
     * @throws \Exception
     */
    private function setupGameWatchdogAndAccess(): void
    {
        // get the watchdog and end-user log-on in order
        $qb = $this->entityManager->getConnection()->createQueryBuilder();
        // not turning game_session into a Doctrine Entity as the whole table will be deprecated
        // as soon as the session API has been ported to Symfony
        $qb->insert('game_session')
            ->values(
                [
                    'game_session_watchdog_address' =>
                        $qb->createPositionalParameter($this->gameSession->getGameWatchdogServer()->getAddress()),
                    'game_session_watchdog_token' => 'UUID_SHORT()',
                    'game_session_password_admin' =>
                        $qb->createPositionalParameter($this->gameSession->getPasswordAdmin()),
                    'game_session_password_player' =>
                        $qb->createPositionalParameter($this->gameSession->getPasswordPlayer() ?? '')
                ]
            )
            ->executeStatement();
        if ($_ENV['APP_ENV'] !== 'test') {
            //Notify the simulation that the game has been set up so we start the simulations.
            //This is needed because MEL needs to be run before the game to setup the initial fishing values.
            //$this->asyncDataTransferTo($game);
            //$game->setProjectDir($this->kernel->getProjectDir());
            $game = new \App\Domain\API\v1\Game();
            $game->setGameSessionId($this->gameSession->getId());
            $game->setAsync(true);
            if (null !== $promise = $game->changeWatchdogState("SETUP")) {
                await($promise);
                $this->gameSessionLogger->info(
                    'Watchdog and user access set up successfully.',
                    ['gameSession' => $this->gameSession->getId()]
                );
                return;
            };
            throw new \Exception('Watchdog failed to start up.');
        } else {
            $this->gameSessionLogger->info(
                'User access set up successfully, but Watchdog was not started as you are in test mode.',
                ['gameSession' => $this->gameSession->getId()]
            );
        }
    }

    /**
     * @throws \Exception
     */
    private function validateGameConfigComplete(): void
    {
        $gameConfigContents = json_decode($this->gameSession->getGameConfigVersion()->getGameConfigCompleteRaw());
        $validator = new Validator();
        $validator->validate(
            $gameConfigContents,
            json_decode(
                file_get_contents($this->kernel->getProjectDir().'/src/Domain/SessionConfigJSONSchema.json')
            )
        );
        if (!$validator->isValid()) {
            $message = '';
            foreach ($validator->getErrors() as $error) {
                $message .= sprintf("[%s] %s".PHP_EOL, $error['property'], $error['message']);
            }
            throw new \Exception($message);
        }
        $this->gameSessionLogger->info(
            'Session config file contents were successfully validated.',
            ['gameSession' => $this->gameSession->getId()]
        );
        $this->dataModel = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel'];
    }
}
