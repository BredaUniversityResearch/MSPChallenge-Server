<?php

namespace App\MessageHandler\GameList;

use App\Controller\SessionAPI\SELController;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\EntityEnums\PlanState;
use App\Domain\Common\EntityEnums\RestrictionSort;
use App\Domain\Common\EntityEnums\RestrictionType;
use App\Domain\Communicator\GeoServerCommunicator;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Helper\Util;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SimulationHelper;
use App\Entity\SessionAPI\LayerRaster;
use App\Logger\GameSessionLogger;
use App\Message\GameList\GameListCreationMessage;
use App\Message\GameSave\GameSaveLoadMessage;
use App\Entity\SessionAPI\Country;
use App\Entity\SessionAPI\EnergyConnection;
use App\Entity\SessionAPI\EnergyOutput;
use App\Entity\SessionAPI\Fishing;
use App\Entity\SessionAPI\Game;
use App\Entity\SessionAPI\Geometry;
use App\Entity\SessionAPI\Grid;
use App\Entity\SessionAPI\GridEnergy;
use App\Entity\SessionAPI\Layer;
use App\Entity\SessionAPI\Objective;
use App\Entity\SessionAPI\Plan;
use App\Entity\SessionAPI\PlanDelete;
use App\Entity\SessionAPI\PlanLayer;
use App\Entity\SessionAPI\PlanMessage;
use App\Entity\SessionAPI\PlanRestrictionArea;
use App\Entity\SessionAPI\Restriction;
use App\Repository\SessionAPI\LayerRepository;
use App\VersionsProvider;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use function App\tc;

#[AsMessageHandler]
class GameListCreationMessageHandler extends CommonSessionHandlerBase
{
    public function __construct(
        KernelInterface $kernel,
        // https://symfony.com/doc/6.4/logging/channels_handlers.html#how-to-autowire-logger-channels:
        //   $<camelCased channel name> + Logger
        LoggerInterface $gameSessionLogger,
        ConnectionManager $connectionManager,
        ContainerBagInterface $params,
        GameSessionLogger $gameSessionLogFileHandler,
        WatchdogCommunicator $watchdogCommunicator,
        VersionsProvider $provider,
        SimulationHelper $simulationHelper,
        private readonly MessageBusInterface $messageBus,
        private readonly HttpClientInterface $client,
        // e.g. used by GeoServerCommunicator
        private readonly CacheInterface $downloadsCache,
        private readonly CacheInterface $resultsCache
    ) {
        parent::__construct(...func_get_args());
    }

    /**
     * @throws \Exception
     */
    public function __invoke(GameListCreationMessage $gameList): void
    {
        $this->setGameSessionAndDatabase($gameList);
        if (!is_null($this->gameSession->getGameSave())) {
            $this->messageBus->dispatch(
                new GameSaveLoadMessage($this->gameSession->getId(), $this->gameSession->getGameSave()->getId())
            );
            return;
        }
        try {
            $this->gameSessionLogFileHandler->empty($this->gameSession->getId());
            $this->validateGameConfig($this->createSessionRunningConfig());
            $this->notice("Session {$this->gameSession->getName()} creation initiated. Please wait.");
            $this->setupSessionDatabase();
            $this->migrateSessionDatabase();
            $this->resetSessionRasterStore();
            $this->entityManager->wrapInTransaction(fn() => $this->setupAllEntities());
            $this->finaliseSession();
            $this->notice("Session {$this->gameSession->getName()} created and ready for use.");
            $state = 'healthy';
        } catch (Throwable $e) {
            $this->error(
                "Session {$this->gameSession->getName()} failed to create. {problem}",
                ['problem' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            $state = 'failed';
        }
        $this->gameSession->setSessionState(new GameSessionStateValue($state));
        $this->gameSession->getGameConfigVersion()->setLastPlayedTime(time());
        $this->mspServerManagerEntityManager->flush();
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Exception
     */
    private function setupSessionDatabase(): void
    {
        if ($this->gameSession->getSessionState() != GameSessionStateValue::REQUEST) {
            $this->notice('Resetting the session database, as this is a session recreate.');
            if ($this->gameSession->getSessionState() != GameSessionStateValue::FAILED) {
                $this->watchdogCommunicator->changeState(
                    $this->gameSession->getId(),
                    new GameStateValue('end'),
                    $this->gameSession->getGameCurrentMonth()
                );
            }
            $this->gameSession->setSessionState(new GameSessionStateValue('request'));
            $this->gameSession->setGameState(new GameStateValue('setup'));
            $this->mspServerManagerEntityManager->flush();
            $this->resetSessionDatabase();
            return;
        }
        $this->notice('Creating a new session database, as this is a brand new session.');
        $this->createSessionDatabase();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function createSessionRunningConfig(): string
    {
        $sessionConfigStore = $this->params->get('app.session_config_dir').
            sprintf($this->params->get('app.session_config_name'), $this->gameSession->getId());
        $gameConfigFilePath = $this->params->get('app.server_manager_config_dir').
            $this->gameSession->getGameConfigVersion()->getFilePath();
        $content = file_get_contents($gameConfigFilePath);
        is_dir(dirname($sessionConfigStore)) or mkdir(dirname($sessionConfigStore), 0777, true);
        file_put_contents($sessionConfigStore, $content);
        $this->info("Created the running session config file at {$sessionConfigStore}");
        return $sessionConfigStore;
    }

    /**
     * setup all entities for a game session *without intermediate flushes*
     *
     * @throws \Exception
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     */
    private function setupAllEntities(): void
    {
        $context = new SessionSetupContext();
        // entities are created in the order of their dependencies
        $this->setupGame();
        $this->setupGameCountries($context);
        $this->importLayerData($context);
        $this->setupRestrictions($context);
        $this->setupSimulations($context);
        $this->setupObjectives($context);
        $this->setupPlans($context);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    private function finaliseSession(): void
    {
        // some final custom queries
        $this->completeGeometryRecords();
        $this->setupGameWatchdogAndAccess();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function setupGame(): void
    {
        $game = new Game();
        $game->setGameId(1);
        $game->setGameConfigfile(sprintf($this->params->get('app.session_config_name'), $this->gameSession->getId()));
        $game->setGameStart($this->dataModel['start']);
        $game->setGamePlanningGametime($this->dataModel['era_planning_months']);
        $game->setGamePlanningRealtime($this->dataModel['era_planning_realtime']);
        $game->setGamePlanningEraRealtimeComplete();
        $game->setGameEratime($this->dataModel['era_total_months']);
        $game->setGameCurrentMonth(-1);
        $this->entityManager->persist($game);
        $this->info('Basic game parameters set up.');
    }

    /**
     * @throws \Exception
     */
    private function setupGameCountries(SessionSetupContext $context): void
    {
        $country1 = (new Country())
            ->setCountryId(1)
            ->setCountryColour($this->dataModel['user_admin_color'])
            ->setCountryIsManager(1);
        $this->entityManager->persist($country1);
        $context->addCountry($country1);
        $country2 = (new Country())
            ->setCountryId(2)
            ->setCountryColour($this->dataModel['user_region_manager_color'])
            ->setCountryIsManager(1);
        $this->entityManager->persist($country2);
        $context->addCountry($country2);
        foreach ($this->dataModel['meta'] as $layerMeta) {
            if ($layerMeta['layer_name'] == $this->dataModel['countries']) {
                foreach ($layerMeta['layer_type'] as $country) {
                    $nextCountry = (new Country())
                        ->setCountryId($country['value'])
                        ->setCountryName($country['displayName'])
                        ->setCountryColour($country['polygonColor'])
                        ->setCountryIsManager(0);
                    $this->entityManager->persist($nextCountry);
                    $context->addCountry($nextCountry);
                }
                break;
            }
        }
        $this->info('All countries set up.');
    }

    /**
     * @param SessionSetupContext $context
     * @return void
     * @throws ClientExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ReflectionException
     * @throws Throwable
     */
    private function importLayerData(SessionSetupContext $context): void
    {
        $username = $this->gameSession->getGameGeoServer()->getUsername();
        $password = $this->gameSession->getGameGeoServer()->getPassword();
        if (empty($username) || empty($password)) {
            tc(fn() => throw new \Exception(
                "No GeoServer credentials provided, so no GeoServer data will be imported."
            ), $this->gameSessionLogger, ['gameSession' => $this->gameSession->getId()]);
        }

        $geoServerCommunicator = new GeoServerCommunicator(
            $this->client,
            $this->provider,
            $this->downloadsCache,
            $this->resultsCache
        );
        $geoServerCommunicator
            ->setBaseURL($this->gameSession->getGameGeoServer()->getAddress())
            ->setUsername($username)
            ->setPassword($password);

        /** @var LayerRepository $layerRepo */
        $layerRepo = $this->entityManager->getRepository(Layer::class);
        foreach ($this->dataModel['meta'] as $layerMetaData) {
            $layer = $layerRepo->denormalize($layerMetaData);
            $layer->setLayerGroup($this->dataModel['region']);
            $this->info("Starting import of layer {$layer->getLayerName()}...");
            if ($layer->getLayerGeoType() == LayerGeoType::RASTER) {
                $this->importLayerRasterData($layer, $geoServerCommunicator);
            } else {
                $this->importLayerGeometryData($layer, $geoServerCommunicator, $context);
            }
            $this->importLayerTypeAvailabilityRestrictions($layer);
            $this->removeLayerGeometryDuplicates($layer);
            $this->entityManager->persist($layer);
            $context->addLayer($layer);
            $this->info("Finished importing layer {$layer->getLayerName()}.");
        }
        $this->checkForDuplicateMspIds($context);
    }

    private function removeLayerGeometryDuplicates(Layer $layer): void
    {
        $geometryCoordsDataSets = [];
        foreach ($layer->getGeometry() as $geometry) {
            $array = [
                'coords' => $geometry->getGeometryGeometry(),
                'data' => $geometry->getGeometryData()
            ];
            if (in_array($array, $geometryCoordsDataSets)) {
                $geometryText = substr($geometry->getGeometryGeometry(), 0, 50).'... - '.
                    substr($geometry->getGeometryData(), 0, 50).'...';
                $this->warning(
                    "Avoided adding duplicate geometry (based on the combination of coordinates and complete ".
                    "properties set) to layer {$layer->getLayerName()}. Some geometry data: {$geometryText}"
                );
                $layer->removeGeometry($geometry);
            } else {
                $geometryCoordsDataSets[] = $array;
            }
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
                $restriction->setRestrictionSort(RestrictionSort::TYPE_UNAVAILABLE);
                $restriction->setRestrictionType(RestrictionType::ERROR);
                $restriction->setRestrictionMessage('Type not yet available at the plan implementation time.');
                $layer->addRestrictionStart($restriction);
                $layer->addRestrictionEnd($restriction);
            }
        }
        if ($counter > 0) {
            $this->info(
                "Added {$counter} temporal availability restrictions for layer {$layer->getLayerName()}'s types."
            );
        }
    }

    /**
     * @param Layer $layer
     * @param GeoServerCommunicator $geoServerCommunicator
     * @throws ClientExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \Exception
     */
    private function importLayerRasterData(
        Layer $layer,
        GeoServerCommunicator $geoServerCommunicator
    ): void {
        if ($layer->getLayerDownloadFromGeoserver()) {
            $this->debug('Calling GeoServer to obtain raster metadata.');
            $rasterMetaData = $geoServerCommunicator->getRasterMetaData(
                $this->dataModel['region'],
                $layer->getLayerName()
            );
            $this->debug("Call to GeoServer completed: {$geoServerCommunicator->getLastCompleteURLCalled()}");
            $this->debug('Calling GeoServer to obtain actual raster data.');
            $layer->setLayerRaster(
                ($layer->getLayerRaster() ?? new LayerRaster())
                    ->setUrl($rasterMetaData['url'])
                    ->setBoundingbox($rasterMetaData['boundingbox'])
            );
            $rasterData = $geoServerCommunicator->getRasterDataByMetaData(
                $this->dataModel['region'],
                $layer,
                $rasterMetaData
            );
            $this->debug("Call to GeoServer completed: {$geoServerCommunicator->getLastCompleteURLCalled()}");

            $layerApi = new \App\Domain\API\v1\Layer();
            $layerApi->setGameSessionId($this->gameSession->getId());
            $layerApi->UpdateRaster($layer, $rasterData);
            foreach ($layerApi->getLogMessages() as $log) {
                $this->debug($log);
            }
            $message = "Successfully retrieved {$layer->getLayerName()} and stored the raster file.";
        }
        $this->info(
            $message ?? "Successfully retrieved {$layer->getLayerName()} without storing a raster file, as requested."
        );
    }

    /**
     * @param Layer $layer
     * @param GeoServerCommunicator $geoServerCommunicator
     * @param SessionSetupContext $context
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function importLayerGeometryData(
        Layer $layer,
        GeoServerCommunicator $geoServerCommunicator,
        SessionSetupContext $context
    ): void {
        if ($layer->getLayerDownloadFromGeoserver()) {
            $this->debug('Calling GeoServer to obtain layer description.');
            $layersContainer = $geoServerCommunicator->getLayerDescription(
                $this->dataModel['region'],
                $layer->getLayerName()
            );
            $this->debug("Call to GeoServer completed: {$geoServerCommunicator->getLastCompleteURLCalled()}");
            foreach ($layersContainer as $layerWithin) {
                $this->debug("Calling GeoServer to obtain geometry features for layer {$layerWithin['layerName']}.");
                $geoserverReturn = $geoServerCommunicator->getLayerGeometryFeatures($layerWithin['layerName']);
                $this->debug("Call to GeoServer completed: {$geoServerCommunicator->getLastCompleteURLCalled()}");
                $features = $geoserverReturn['features']
                    ?? throw new \Exception(
                        'Geometry data call did not return a features variable, so something must be wrong.'
                    );
                $numFeatures = count($features);
                $this->debug("Starting import of all {$numFeatures} layer geometry features.");
                foreach ($features as $feature) {
                    $layerWithinParts = explode(':', $layerWithin['layerName']);
                    $feature['properties']['original_layer_name'] = $layerWithinParts[1] ?? $layerWithinParts[0];
                    $geometryTypeAdded = $this->addLayerGeometryFromFeatureDataSet($layer, $feature, $context);
                    isset($counter[$geometryTypeAdded]) ?
                        $counter[$geometryTypeAdded]++ : $counter[$geometryTypeAdded] = 1;
                }
                $geometryTypeDetails = http_build_query($counter ?? '', '', ' ');
                $this->debug("Import of layer geometry features completed: {$geometryTypeDetails}.");
                if (isset($counter['None'])) {
                    $this->warning("A total of {$counter['None']} features returned no geometry at all.");
                }
            }
            $message = "Successfully retrieved {$layer->getLayerName()} and stored the geometry in the database.";
            if ($layer->hasGeometryWithGeneratedMspids()) {
                $message .= ' Note that at least some of the geometry has auto-generated MSP IDs.';
            }
        }
        $this->info(
            $message ?? "Successfully set up {$layer->getLayerName()} without obtaining geometry, as requested."
        );
    }

    /**
     * @param Layer $layer
     * @param array $feature
     * @param SessionSetupContext $context
     * @return string
     */
    private function addLayerGeometryFromFeatureDataSet(
        Layer $layer,
        array $feature,
        SessionSetupContext $context
    ): string {
        $geometryData = $feature['geometry'];
        if (empty($geometryData)) {
            return 'None';
        }
        self::ensureMultiData($geometryData);

        $feature['properties']['country_object'] = null;
        if ((null !== $country = $context->getCountry($feature['properties']['country_id'] ?? -1))) {
            $feature['properties']['country_object'] = $country;
        }
        if (strcasecmp($geometryData['type'], 'MultiPolygon') == 0) {
            foreach ($geometryData['coordinates'] as $multiPolygon) {
                if (!is_array($multiPolygon)) {
                    continue;
                }
                $geometryToSubtractFrom = null;
                foreach ($multiPolygon as $key => $polygon) {
                    $geometry = new Geometry($layer);
                    $geometry
                        ->setGeometryGeometry($polygon)
                        ->setGeometryPropertiesThroughFeature($feature['properties'])
                        ->setGeometryToSubtractFrom($geometryToSubtractFrom);
                    $layer->addGeometry($geometry);
                    if (sizeof($multiPolygon) > 1 && $key == 0) {
                        $geometryToSubtractFrom = $geometry;
                    }
                }
            }
        } elseif (strcasecmp($geometryData['type'], 'MultiLineString') == 0) {
            foreach ($geometryData['coordinates'] as $line) {
                $geometry = new Geometry($layer);
                $geometry
                    ->setGeometryGeometry($line)
                    ->setGeometryPropertiesThroughFeature($feature['properties']);
                $layer->addGeometry($geometry);
            }
        } elseif (strcasecmp($geometryData['type'], 'MultiPoint') == 0) {
            $geometry = new Geometry($layer);
            $geometry
                ->setGeometryGeometry($geometryData["coordinates"])
                ->setGeometryPropertiesThroughFeature($feature['properties']);
            $layer->addGeometry($geometry);
        }
        return $geometryData['type'];
    }

    public function checkForDuplicateMspIds(SessionSetupContext $context): void
    {
        $geometries = $context->getGeometriesWithDuplicateMspId();
        if (empty($geometries)) {
            $this->info("No duplicate MSP IDs. Yay!");
            return;
        }
        $this->error("Duplicate MSP IDs found.");
        foreach ($geometries as $mspId => $geometryList) {
            $counted = count($geometryList);
            $this->error("MSP ID {$mspId} has {$counted} duplicates");
            $previousGeometryData = null;
            foreach ($geometryList as $key => $geometry) {
                $geometryData = $geometry->getGeometryData();
                if ($previousGeometryData === null) {
                    $this->error(
                        "MSP ID {$mspId} was used in layer {$geometry->getLayer()->getLayerName()} ".
                        "for a feature with properties {$geometryData}"
                    );
                    $previousGeometryData = $geometryData;
                } else {
                    if ($previousGeometryData !== $geometryData) {
                        $this->error("...and for a feature with somehow differing properties: {$geometryData}");
                    } else {
                        $this->error("...and for another feature but seemingly with the same properties.");
                    }
                }
                if ($key == 4 && count($geometryList) > 5) {
                    $this->error("Now terminating the listing of duplicated geometry to not clog up this log.");
                    break;
                }
            }
        }
    }

    public static function ensureMultiData(&$geometry): void
    {
        if ($geometry['type'] == 'Polygon' || $geometry['type'] == 'LineString' ||  $geometry['type'] == 'Point') {
            $geometry['coordinates'] = [$geometry['coordinates']];
            $geometry['type'] = 'Multi'.$geometry['type'];
        }
    }

    /**
     * @param SessionSetupContext $context
     */
    private function setupRestrictions(SessionSetupContext $context): void
    {
        if (empty($this->dataModel['restrictions'])) {
            $this->info('No layer restrictions to set up.');
            return;
        }
        $count = count($this->dataModel['restrictions']);
        $this->info("Found {$count} restriction definitions, commencing setup.");
        foreach ($this->dataModel['restrictions'] as $restrictionKey => $restrictionConfig) {
            $restrictionKeyText = (int) $restrictionKey + 1;
            foreach ($restrictionConfig as $restrictionItem) {
                $restriction = new Restriction();
                if (null === $startLayer = $context->getLayer($restrictionItem['startlayer'] ?? '')) {
                    $this->warning(
                        "Start layer {$restrictionItem['startlayer']} used in restriction {$restrictionKeyText} ".
                        "does not seem to exist. Are you sure this layer has been added under the 'meta' object? ".
                        "Restriction skipped."
                    );
                    continue;
                }
                if (null === $endLayer = $context->getLayer($restrictionItem['endlayer'] ?? '')) {
                    $this->warning(
                        "End layer {$restrictionItem['endlayer']} used in restriction {$restrictionKeyText} does ".
                        "not seem to exist. Are you sure this layer has been added under the 'meta' object? ".
                        "Restriction skipped."
                    );
                    continue;
                }
                $restriction->setRestrictionStartLayer($startLayer)
                    ->setRestrictionEndLayer($endLayer)
                    ->setRestrictionSort(RestrictionSort::from(strtoupper($restrictionItem['sort'])))
                    ->setRestrictionValue($restrictionItem['value'])
                    ->setRestrictionType(RestrictionType::from(strtoupper($restrictionItem['type'])))
                    ->setRestrictionMessage($restrictionItem['message'])
                    ->setRestrictionStartLayerType($restrictionItem['starttype'])
                    ->setRestrictionEndLayerType($restrictionItem['endtype']);
                $this->entityManager->persist($restriction);
            }
        }
        $this->info('Restrictions setup complete.');
    }

    /**
     * @param SessionSetupContext $context
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function setupSimulations(SessionSetupContext $context): void
    {
        $simulationsDone = [];
        $possibleSims = array_keys($this->provider->getComponentsVersions());
        foreach ($possibleSims as $possibleSim) {
            if (array_key_exists($possibleSim, $this->dataModel)
                && is_array($this->dataModel[$possibleSim])
                && $this->createSessionForSimulation($possibleSim, $context)
            ) {
                $simulationsDone[] = $possibleSim;
            }
        }
        $remainingSims = array_diff($possibleSims, $simulationsDone);
        foreach ($remainingSims as $remainingSim) {
            if (key_exists($remainingSim, $this->dataModel)) {
                $this->error("Unable to set up {$remainingSim}, as its SessionCreation function does not exist.");
            }
        }
    }

    /**
     * @param string $simulation
     * @param SessionSetupContext $context
     * @return bool
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws \Exception
     */
    private function createSessionForSimulation(string $simulation, SessionSetupContext $context): bool
    {
        switch ($simulation) {
            case 'MEL':
                $this->MELSessionCreation($context);
                return true;
            case 'SEL':
                $this->SELSessionCreation($context);
                return true;
            case 'CEL':
                $this->CELSessionCreation();
                return true;
            default:
                return false;
        }
    }

    /**
     * @param SessionSetupContext $context
     * @throws \Exception
     */
    private function MELSessionCreation(SessionSetupContext $context): void
    {
        $this->info('Setting up simulation MEL...');
        $config = $this->dataModel['MEL'];
        if (isset($config["fishing"])) {
            $countries = $context->getCountries(
                fn(Country $country) => $country->getCountryIsManager() == 0
            );
            foreach ($config["fishing"] as $fleet) {
                if (isset($fleet["initial_fishing_distribution"])) {
                    foreach ($countries as $country) {
                        $foundCountry = false;
                        foreach ($fleet["initial_fishing_distribution"] as $distribution) {
                            if ($distribution["country_id"] == $country->getCountryId()) {
                                $foundCountry = true;
                                break;
                            }
                        }
                        if (!$foundCountry) {
                            $this->error(
                                "Country with ID {$country->getCountryId()} is missing a distribution entry ".
                                "in the initial_fishing_distribution table for fleet {$fleet["name"]} for MEL."
                            );
                        }
                    }
                }
            }
        }

        foreach ($config['pressures'] as $pressure) {
            $pressureLayer = $this->setupMELLayer($pressure['name'], $context);
            foreach ($pressure['layers'] as $layerGeneratingPressures) {
                if (null === $layer = $context->getLayer($layerGeneratingPressures['name'] ?? '')) {
                    continue;
                }
                //add a layer to the mel_layer table for faster accessing
                // $layer is cascaded by $pressureLayer, so no need to persist it
                $pressureLayer->addPressureGeneratingLayer($layer);
            }
            $this->entityManager->persist($pressureLayer);
        }
        foreach ($config['outcomes'] as $outcome) {
            $outcomeLayer = $this->setupMELLayer($outcome['name'], $context);
            $this->entityManager->persist($outcomeLayer);
        }
        $this->info('Finished setting up simulation MEL.');
    }

    /**
     * @param string $melLayerName
     * @param SessionSetupContext $context
     * @return Layer
     * @throws \Exception
     */
    private function setupMELLayer(string $melLayerName, SessionSetupContext $context): Layer
    {
        $layerName = "mel_" . str_replace(" ", "_", $melLayerName);
        if (null === $layer = $context->getLayer($layerName)) {
            throw new \Exception(
                "Pressure layer {$layerName} not found. Make sure it has been defined under 'meta'."
            );
        }
        $layer->setLayerRaster(
            ($layer->getLayerRaster() ?? new LayerRaster())
            ->setUrl("{$layerName}.tif")
            ->setBoundingbox([
                [
                    $this->dataModel['MEL']['x_min'],
                    $this->dataModel['MEL']["y_min"]
                ],
                [
                    $this->dataModel['MEL']["x_max"],
                    $this->dataModel['MEL']["y_max"]
                ]
            ])
        );
        return $layer;
    }

    /**
     * @throws \Exception
     */
    private function getPlayAreaGeometryFromContext(SessionSetupContext $context): Geometry
    {
        $playAreaLayers = $context->filterLayers(
            fn(Layer $l, $k) => null !== Util::hasPrefix($l->getLayerName(), LayerRepository::PLAY_AREA_LAYER_PREFIX)
        );
        $this->info('Found '.count($playAreaLayers).' player area layer(s)');
        return SELController::getGeometryWithLargestBounds(collect($playAreaLayers)->map(
            fn(Layer $layer) => $layer->getGeometry()->first()
        )->all());
    }

    /**
     * @param SessionSetupContext $context
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws \Exception
     */
    private function SELSessionCreation(SessionSetupContext $context): void
    {
        $this->info('Setting up simulation SEL...');
        $boundsConfig = SELController::calculateAlignedSimulationBounds(
            $this->dataModel,
            $this->getPlayAreaGeometryFromContext($context)
        );
        foreach ($this->dataModel["SEL"]["heatmap_settings"] as $heatmap) {
            if (null === $selOutputLayer = $context->getLayer($heatmap['layer_name'] ?? '')) {
                throw new \Exception(
                    'The layer '.$heatmap['layer_name'].' referenced in the heatmap settings has not been 
                    found in the database, so cannot continue. Are you sure it has been defined separately as an 
                    actual layer in the configuration file?'
                );
            }
            $layerRaster = ($selOutputLayer->getLayerRaster() ?? new LayerRaster());
            $selOutputLayer->setLayerRaster(
                $layerRaster->setUrl("{$selOutputLayer->getLayerName()}.png")
            );
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
                $layerRaster->setBoundingbox([
                    [$this->dataModel["MEL"]['x_min'], $this->dataModel["MEL"]['y_min']],
                    [$this->dataModel["MEL"]['x_max'], $this->dataModel["MEL"]['y_max']]
                ]);
            } else {
                $layerRaster->setBoundingbox([
                    [$boundsConfig['x_min'], $boundsConfig['y_min']],
                    [$boundsConfig['x_max'], $boundsConfig['y_max']]
                ]);
            }
            $this->entityManager->persist($selOutputLayer);
        }
        $this->info('Finished setting up simulation SEL.');
    }

    private function CELSessionCreation(): void
    {
        $this->info('For CEL no setup is required at all. Ready to go.');
    }

    /**
     * @param SessionSetupContext $context
     * @throws ExceptionInterface
     * @throws \Exception
     */
    private function setupPlans(SessionSetupContext $context): void
    {
        if (empty($this->dataModel['plans'])) {
            $this->info('No plans defined, so nothing to import there.');
        }
        foreach ($this->dataModel['plans'] as $planConfig) {
            /** @var Plan $plan */
            $plan = $this->normalizer->denormalize($planConfig, Plan::class, null, [
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['fishing']
            ]);
            $this->info("Starting import of plan {$plan->getPlanName()}.");
            $plan->setCountry($context->getCountry($planConfig['plan_country_id'] ?? -1));
            $plan->setPlanState(PlanState::APPROVED);
            foreach ($planConfig['fishing'] as $fishingConfig) {
                $fishing = $this->normalizer->denormalize($fishingConfig, Fishing::class);
                $fishing->setCountry($context->getCountry($fishingConfig['fishing_country_id'] ?? -1));
                // $fishing is cascaded by $plan, so no persist needed
                $plan->addFishing($fishing);
            }

            foreach ($planConfig['messages'] as $planMessageConfig) {
                $planMessage = new PlanMessage();
                $planMessage->setCountry($context->getCountry($planMessageConfig['country_id'] ?? -1));
                $planMessage->setPlanMessageUserName($planMessageConfig['user_name']);
                $planMessage->setPlanMessageText($planMessageConfig['text']);
                $planMessage->setPlanMessageTime($planMessageConfig['time']);
                // $planMessage is cascaded by $plan, so no persist needed
                $plan->addPlanMessage($planMessage);
            }

            foreach ($planConfig['restriction_settings'] as $restrictionAreaConfig) {
                $planRestrictionArea = new PlanRestrictionArea();
                $planRestrictionArea->setLayer($context->getLayer($restrictionAreaConfig['layer_name'] ?? ''));
                $planRestrictionArea->setCountry(
                    $context->getCountry($restrictionAreaConfig['country_id'] ?? -1)
                );
                $planRestrictionArea->setPlanRestrictionAreaEntityType($restrictionAreaConfig['entity_type_id']);
                $planRestrictionArea->setPlanRestrictionAreaSize($restrictionAreaConfig['size']);
                // $planRestrictionArea is cascaded by $plan, so no persist needed
                $plan->addPlanRestrictionArea($planRestrictionArea);
            }
            $this->setupPlannedLayerGeometry($planConfig, $plan, $context);
            $this->setupPlannedGrids($planConfig['grids'], $plan, $context);
            $plan->updatePlanConstructionTime();
            $this->info("Import of plan {$plan->getPlanName()} finished.");
            $this->entityManager->persist($plan);
        }
    }

    private function completeGeometryRecords(): void
    {
        // final step to avoid client complaints, note that this likely confuses Doctrine a bit, hence at the end
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update('App:Geometry', 'g')
            ->set('g.originalGeometry', 'g.geometryId')
            ->where($qb->expr()->isNull('g.originalGeometry'))
            ->getQuery()
            ->execute();
    }

    /**
     * @param array $planConfig
     * @param Plan $plan
     * @param SessionSetupContext $context
     * @throws \Exception
     */
    private function setupPlannedLayerGeometry(array $planConfig, Plan $plan, SessionSetupContext $context): void
    {
        $planCableConnectionsConfig = [];
        $planEnergyOutputConfig = [];
        $this->info(
            "Starting import of plan {$plan->getPlanName()}'s {count} layers.",
            ['count' => count($planConfig['layers'])]
        );
        foreach ($planConfig['layers'] as $layerConfig) {
            $derivedLayer = new Layer();
            foreach ($layerConfig['geometry'] as $layerGeometryConfig) {
                $geometry = new Geometry();
                $geometry
                    ->setOldGeometryId($layerGeometryConfig['geometry_id'])
                    ->setGeometryData($layerGeometryConfig['data'] ?? null)
                    ->setGeometryFID($layerGeometryConfig['FID'])
                    ->setGeometryGeometry($layerGeometryConfig['geometry'])
                    ->setGeometryType($layerGeometryConfig['type']);
                $context->addGeometry($geometry);
                if ((null !== $country = $context->getCountry($layerGeometryConfig['country'] ?? -1))) {
                    $country->addGeometry($geometry);
                }
                if (null !== $originalGeometry = $this->findNewPersistentGeometry(
                    $layerGeometryConfig['base_geometry_info'],
                    $context,
                    $geometry
                )) {
                    // $originalGeometry is cascaded by $geometry, so no persist needed
                    $originalGeometry->addDerivedGeometry($geometry);
                }
                // $geometry is cascaded by $derivedLayer, so no persist needed
                $derivedLayer->addGeometry($geometry);
                if (!empty($layerGeometryConfig['cable'])) {
                    $planCableConnectionsConfig[] = array_merge(
                        $layerGeometryConfig['cable'],
                        $layerGeometryConfig['base_geometry_info']
                    );
                }
                if (!empty($layerGeometryConfig['energy_output'])) {
                    $planEnergyOutputConfig[] = array_merge(
                        $layerGeometryConfig['energy_output'],
                        $layerGeometryConfig['base_geometry_info']
                    );
                }
            }
            // $planLayer is cascaded by $plan, so no persist needed
            $planLayer = new PlanLayer();
            // $derivedLayer is cascaded by $planLayer (and vice versa), so no persist needed.
            //  And layers were persisted before in the importLayerData method.
            $derivedLayer->addPlanLayer($planLayer);
            $layer = $context->getLayer($layerConfig['name'] ?? '');
            // $layer is cascaded by $derivedLayer (and vice versa), so no persist needed
            //  And layers were persisted before in the importLayerData method.
            $layer?->addDerivedLayer($derivedLayer);
            // $plan is already persisted by caller.
            $plan->addPlanLayer($planLayer);
            foreach ($layerConfig['deleted'] as $layerGeometryDeletedConfig) {
                $planDelete = new PlanDelete();
                // $planDelete is cascaded by $derivedLayer, so no persist needed
                $derivedLayer->addPlanDelete($planDelete);
                $plan->addPlanDelete($planDelete);
                $originalPlannedDeletedGeometry = $this->findNewPersistentGeometry(
                    $layerGeometryDeletedConfig['base_geometry_info'],
                    $context
                );
                if (null === $originalPlannedDeletedGeometry) {
                    continue;
                }
                // $originalPlannedDeletedGeometry is cascaded by $planDelete (and vice versa), so no persist needed
                $originalPlannedDeletedGeometry->addPlanDelete($planDelete);
            }
        }
        $this->setupPlannedCableConnections($planCableConnectionsConfig, $context);
        $this->setupPlannedEnergyOutput($planEnergyOutputConfig, $context);
    }

    /**
     * @param array $cablesConfig
     * @param SessionSetupContext $context
     * @throws \Exception
     */
    private function setupPlannedCableConnections(array $cablesConfig, SessionSetupContext $context): void
    {
        //Import energy connections now we know all geometry is known by the importer.
        foreach ($cablesConfig as $cableConfig) {
            $energyConnection = new EnergyConnection();
            $energyConnection->setCableGeometry($this->findNewGeometry($cableConfig, $context));
            $energyConnection->setStartGeometry($this->findNewGeometry($cableConfig['start'], $context));
            $energyConnection->setEndGeometry($this->findNewGeometry($cableConfig['end'], $context));
            $energyConnection->setEnergyConnectionStartCoordinates($cableConfig['coordinates']);
            $energyConnection->setEnergyConnectionLastupdate(100);
            $this->entityManager->persist($energyConnection);
        }
    }

    /**
     * @param array $energyOutputsConfig
     * @param SessionSetupContext $context
     * @throws \Exception
     */
    private function setupPlannedEnergyOutput(array $energyOutputsConfig, SessionSetupContext $context): void
    {
        foreach ($energyOutputsConfig as $energyOutputConfig) {
            $energyOutput = new EnergyOutput();
            $energyOutput->setGeometry($this->findNewGeometry($energyOutputConfig, $context));
            $energyOutput->setEnergyOutputMaxcapacity($energyOutputConfig[0]['maxcapacity']);
            $energyOutput->setEnergyOutputActive($energyOutputConfig[0]['active']);
            $this->entityManager->persist($energyOutput);
        }
    }

    /**
     * @throws \Exception
     */
    private function setupPlannedGrids(?array $planGridsConfig, Plan $plan, SessionSetupContext $context): void
    {
        foreach ($planGridsConfig as $gridConfig) {
            $grid = new Grid();
            $grid->setGridName($gridConfig['name']);
            $grid->setGridActive($gridConfig['active']);
            $grid->setGridLastupdate(100);
            $grid->setPlan($plan);
            $this->entityManager->persist($grid);
            if ($gridConfig['grid_persistent'] == $gridConfig['grid_id']) {
                $context->addGrid($gridConfig['grid_id'], $grid);
            }
            if (null === $originalGrid = $context->getGrid($gridConfig['grid_persistent'])) {
                throw new \Exception("Found reference persistent Grid ID (". $gridConfig['grid_persistent'].
                    ") which has not been imported by the plans importer (yet).");
            }
            // $originalGrid is cascaded by $grid, so no persist needed
            $grid->setOriginalGrid($originalGrid);
            foreach ($gridConfig['energy'] as $gridEnergyConfig) {
                $gridEnergy = new GridEnergy();
                $gridEnergy->setCountry($context->getCountry($gridEnergyConfig['country'] ?? -1));
                $gridEnergy->setGridEnergyExpected($gridEnergyConfig['expected']);
                // $gridEnergy is cascaded by $grid, so no persist needed
                $grid->addGridEnergy($gridEnergy);
            }
            if (is_array($gridConfig['removed'])) {
                foreach ($gridConfig['removed'] as $gridRemovedConfig) {
                    if (null === $gridRemoved = $context->getGrid($gridRemovedConfig['grid_persistent'])) {
                        throw new \Exception("Found plan to remove grid ({$gridRemovedConfig['grid_persistent']}" .
                            ") but this has not been imported by the plans importer (yet).");
                    }
                    $plan->addGridToRemove($gridRemoved);
                }
            }
            if (is_array($gridConfig['sockets'])) {
                foreach ($gridConfig['sockets'] as $gridSocketConfig) {
                    $grid->addSocketGeometry($this->findNewGeometry($gridSocketConfig['geometry'], $context));
                }
            }
            if (is_array($gridConfig['sources'] ?? [])) {
                foreach ($gridConfig['sources'] ?? [] as $gridSourceConfig) {
                    $grid->addSourceGeometry($this->findNewGeometry($gridSourceConfig['geometry'], $context));
                }
            }
        }
    }

    /**
     * When importing the geometry included in the 'plans' part of the config file, we'll need to map the geometry IDs
     * in there to the IDs given to that same geometry as it was imported into the database earlier...
     * ... and we'll need to be able to map *references* to any original geometry in there to the original geometry
     * as it was imported into the database earlier. For the second purpose we have this function.
     * @param array $baseGeometryInfo
     * @param SessionSetupContext $context
     * @param Geometry|null $geometry
     * @return Geometry|null
     * @throws \Exception
     */
    private function findNewPersistentGeometry(
        array               $baseGeometryInfo,
        SessionSetupContext $context,
        ?Geometry           $geometry = null
    ): ?Geometry {
        if (!empty($baseGeometryInfo['geometry_mspid'])) {
            return $context->findOneGeometryByIdentifier(
                new GeometryIdentifierType(GeometryIdentifierType::MSP_ID),
                $baseGeometryInfo['geometry_mspid']
            ); // as MSP IDs are meant to always stay the same across any session and config file
        }
        if (null !== $originalGeometry = $context->findOneGeometryByIdentifier(
            new GeometryIdentifierType(GeometryIdentifierType::OLD_ID),
            $baseGeometryInfo["geometry_persistent"]
        )) {
            // this means that the original (persistent) geometry being referred to was already put in the database
            return $originalGeometry;
        }
        if (!empty($baseGeometryInfo['geometry_id']) && !empty($baseGeometryInfo['geometry_persistent']) &&
            $baseGeometryInfo['geometry_id'] == $baseGeometryInfo['geometry_persistent']) {
            return $geometry; // the geometry in this plan is completely new anyway, so it *is* the original
        }
        throw new \Exception(
            "Failed to find newly imported persistent geometry. No MSP ID was available, geometry_persistent ".
            "{$baseGeometryInfo["geometry_persistent"]} wasn't imported earlier, and this isn't new geometry.".
            var_export($baseGeometryInfo, true)
        );
    }

    /**
     * When importing the geometry included in the 'plans' part of the config file, we'll need to map the geometry IDs
     * in there to the IDs given to that same geometry as it was imported into the database earlier...
     * ... and we'll need to be able to map *references* to any original geometry in there to the original geometry
     * as it was imported into the database earlier. For the first purpose we have this function.
     * @param array $baseGeometryInfo
     * @param SessionSetupContext $context
     * @return Geometry|null
     * @throws \Exception
     */
    private function findNewGeometry(array $baseGeometryInfo, SessionSetupContext $context): ?Geometry
    {
        if (null !== $geometry = $context->findOneGeometryByIdentifier(
            new GeometryIdentifierType(GeometryIdentifierType::OLD_ID),
            $baseGeometryInfo['geometry_id']
        )) {
            return $geometry;
        }
        // If we can't find the geometry id in the ones that we already have imported, check if the geometry id
        //   matches the persistent id, and if so select it by the mspid since this should all be present then.
        if ($baseGeometryInfo["geometry_id"] == $baseGeometryInfo["geometry_persistent"]) {
            if (isset($baseGeometryInfo["geometry_mspid"])) {
                return $context->findOneGeometryByIdentifier(
                    new GeometryIdentifierType(GeometryIdentifierType::MSP_ID),
                    $baseGeometryInfo['geometry_mspid']
                );
            }
            throw new \Exception("Found geometry (".implode(", ", $baseGeometryInfo).
                " which has not been imported by the plans importer. The persistent id matches but MSP ID is not set.");
        }
        throw new \Exception("Found geometry ID (Fallback field \"geometry_id\": ". $baseGeometryInfo["geometry_id"].
            ") which hasn't been imported by the plans importer yet.");
    }

    /**
     * @param SessionSetupContext $context
     * @return void
     */
    private function setupObjectives(SessionSetupContext $context): void
    {
        if (empty($this->dataModel['objectives'])) {
            $this->info('No objectives to set up.');
            return;
        }
        foreach ($this->dataModel['objectives'] as $key => $objectiveConfig) {
            if (null === $country = $context->getCountry($objectiveConfig['country_id'] ?? -1)) {
                $this->warning(
                    'Country {countryId} set in objective {key} does not seem to exist.',
                    ['key' => $key, 'countryId' => $objectiveConfig['country_id']]
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
        $this->info(
            'A total of {count} objectives were set up successfully.',
            ['count' => count($this->dataModel['objectives'])]
        );
    }

    /**
     * @throws \Exception
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    private function setupGameWatchdogAndAccess(): void
    {
        // not turning game_session into a Doctrine Entity as the whole table will be deprecated
        // as soon as the session API has been ported to Symfony, so this is just for backward compatibility
        $this->gameSession->encodePasswords();
        $qb = $this->entityManager->getConnection()->createQueryBuilder();
        $qb->insert('game_session')
            ->values(
                [
                    'game_session_password_admin' =>
                        $qb->createPositionalParameter($this->gameSession->getPasswordAdmin()),
                    'game_session_password_player' =>
                        $qb->createPositionalParameter($this->gameSession->getPasswordPlayer() ?? '')
                ]
            )
            ->executeStatement();

        $this->registerSimulations();
        $this->watchdogCommunicator->changeState(
            $this->gameSession->getId(),
            new GameStateValue('setup'),
            $this->gameSession->getGameCurrentMonth()
        );
        $this->logContainer($this->watchdogCommunicator);
    }
}
