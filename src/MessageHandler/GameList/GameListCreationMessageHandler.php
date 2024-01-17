<?php

namespace App\MessageHandler\GameList;

use App\Entity\Country;
use App\Entity\Game;
use App\Domain\API\v1\Simulations;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Communicator\GeoServerCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Entity\Layer;
use App\Entity\ServerManager\GameList;
use App\Message\GameList\GameListCreationMessage;
use App\Repository\ServerManager\GameListRepository;
use App\VersionsProvider;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

use function App\await;

#[AsMessageHandler]
class GameListCreationMessageHandler
{
    private string $database;
    private EntityManagerInterface $entityManager;

    private GameList $gameSession;

    private array $dataModel;

    public function __construct(
        private readonly EntityManagerInterface $mspServerManagerEntityManager,
        private readonly GameListRepository $gameListRepository,
        private readonly LoggerInterface $gameSessionLogger,
        private readonly HttpClientInterface $client,
        private readonly KernelInterface $kernel,
        private readonly ContainerBagInterface $params,
        private readonly VersionsProvider $provider
    ) {
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function __invoke(GameListCreationMessage $gameList): void
    {
        $this->gameSession = $this->gameListRepository->find($gameList->id)
            ?? throw new \Exception('Game session not found, so cannot continue.');
        $connectionManager = ConnectionManager::getInstance();
        $this->database = $connectionManager->getGameSessionDbName($this->gameSession->getId());
        $this->entityManager = $this->kernel->getContainer("doctrine.orm.{$this->database}_entity_manager");

        try {
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
     */
    private function finaliseSession(): void
    {
        $this->setupGame();
        $this->setupGameCountries();
        $this->importLayerData();
        $this->setupLayerMeta();
        $this->setupRestrictions();
        $this->setupSimulations();
        $this->setupObjectives();
        $this->setupPlans();
        $this->setupGameWatchdogAndAccess();
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
        $this->entityManager->flush();
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
        $this->entityManager->flush();
        $this->gameSessionLogger->info(
            'All countries set up.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    private function importLayerData(): void
    {
        $geoServerCommunicator = new GeoServerCommunicator($this->client);
        $geoServerCommunicator->setBaseURL($this->gameSession->getGameGeoServer()->getAddress());
        $geoServerCommunicator->setUsername($this->gameSession->getGameGeoServer()->getUsername());
        $geoServerCommunicator->setPassword($this->gameSession->getGameGeoServer()->getPassword());

        foreach ($this->dataModel['meta'] as $layerMetaData) {
            $this->gameSessionLogger->info(
                'Starting import of layer {layerName}...',
                ['gameSession' => $this->gameSession->getId(), 'layerName' => $layerMetaData['layer_name']]
            );
            $startTime = microtime(true);

            if ($layerMetaData['layer_geotype'] == "raster") {
                $this->importLayerRasterData(
                    $layerMetaData,
                    $geoServerCommunicator
                );
            } else {
                $this->importLayerGeometryData(
                    $layerMetaData,
                    $geoServerCommunicator
                );
            }
            $this->gameSessionLogger->info(
                'Imported layer {layerName} in {seconds} seconds.',
                [
                    'gameSession' => $this->gameSession->getId(),
                    'layerName' => $layerMetaData['layer_name'],
                    'seconds' => (microtime(true) - $startTime)
                ]
            );
        }
    }

    /**
     * @throws Exception
     * @throws \Exception
     * @param array<string, string> $layerMetaData
     */
    private function importLayerRasterData(
        array $layerMetaData,
        GeoServerCommunicator $geoServerCommunicator
    ): void {
        $layer = new Layer();
        $layer->setLayerName($layerMetaData['layer_name']);
        $layer->setLayerGeotype($layerMetaData['layer_geotype']);
        $layer->setLayerGroup($this->dataModel['region']);
        $layer->setLayerEditable(0);
        $message = 'Successfully retrieved {layerName} without storing a raster file, as requested.';
        $rasterFileName = $_ENV['APP_ENV'] == 'test' ?
            $this->params->get('app.session_raster_dir_test') :
            $this->params->get('app.session_raster_dir');
        $rasterFileName .= "{$this->gameSession->getId()}/{$layerMetaData['layer_name']}.png";
        if (!array_key_exists('layer_download_from_geoserver', $layerMetaData) ||
            $layerMetaData['layer_download_from_geoserver']
        ) {
            $rasterMetaData = $geoServerCommunicator->getRasterMetaData(
                $this->dataModel['region'],
                $layerMetaData['layer_name']
            );
            $layer->setLayerRaster($rasterMetaData);
            $rasterData = $geoServerCommunicator->getRasterDataThroughMetaData(
                $this->dataModel['region'],
                $layerMetaData,
                $rasterMetaData
            );
            $fileSystem = new Filesystem();
            $fileSystem->dumpFile(
                $rasterFileName,
                $rasterData
            );
            $message = 'Successfully retrieved {layerName} and stored the raster file at {rasterFileName}.';
        }
        // Create the metadata for the raster layer, but don't fill in the layer_raster field.
        $this->entityManager->persist($layer);
        $this->entityManager->flush();
        $this->gameSessionLogger->info(
            $message,
            [
                'gameSession' => $this->gameSession->getId(),
                'layerName' => $layerMetaData['layer_name'],
                'rasterFileName' => $rasterFileName
            ]
        );
    }

    /**
     * @throws \Exception
     * @param array<string, string> $layerMetaData
     */
    private function importLayerGeometryData(
        array $layerMetaData,
        GeoServerCommunicator $geoServerCommunicator
    ): void {
        $layer = new Layer();
        $layer->setLayerName($layerMetaData['layer_name']);
        $layer->setLayerGeotype($layerMetaData['layer_geotype']);
        $layer->setLayerGroup($this->dataModel['region']);
        $this->entityManager->persist($layer);
        $this->entityManager->flush();
        $message = 'Successfully retrieved {layerName} without storing geometry in the database, as requested.';
        if (!array_key_exists('layer_download_from_geoserver', $layerMetaData) ||
            $layerMetaData['layer_download_from_geoserver']
        ) {
            $layersContainer = $geoServerCommunicator->getLayerDescription(
                $this->dataModel['region'],
                $layerMetaData['layer_name']
            );
            foreach ($layersContainer as $layerWithin) {
                $geometryData = $geoServerCommunicator->getLayerGeometry($layerWithin['layerName']);
                $features = $geometryData['features']
                    ?? throw new \Exception(
                        'Geometry data call did not return a features variable, so something must be wrong.'
                    );
                foreach ($features as $feature) {
                    if (empty($feature["geometry"])) {
                        $this->gameSessionLogger->error(
                            'Could not import geometry with id {featureId} of layer '.
                            ' {layerName}. The feature in question has NULL geometry.'.
                            ' Some property information to help you find the feature: ',
                            [
                                'featureId' => $feature["id"],
                                'layerName' => $layerWithin['layerName'],
                                'propertyInfo' => substr(
                                    var_export($feature["properties"], true),
                                    0,
                                    80
                                ),
                                'gameSession' => $this->gameSession->getId()
                            ]
                        );
                        continue;
                    }
                    if (!$this->processAndAdd($feature, $layer->getLayerId(), $layerMetaData)) {
                        $this->gameSessionLogger->error(
                            'Could not import geometry with id {featureId} of layer {layerName} into database.'.
                            ' Some property information to help you find the feature: ',
                            [
                                'featureId' => $feature['id'],
                                'layerName' => $layerWithin['layerName'],
                                'propertyInfo' => substr(
                                    var_export($feature['properties'], true),
                                    0,
                                    80
                                ),
                                'gameSession' => $this->gameSession->getId()
                            ]
                        );
                    }
                }
            }
            $message = 'Successfully retrieved {layerName} and stored the geometry in the database.';
        }
        // Create the metadata for the vector layer, but don't fill the geometry table.
        $this->gameSessionLogger->info(
            $message,
            ['gameSession' => $this->gameSession->getId(), 'layerName' => $layerMetaData['layer_name']]
        );
    }

    public function processAndAdd($feature, $layerId, $layerMetaData): bool
    {
        $feature = $this->moveDataFromArray($layerMetaData, $feature);
        if ($this->featureHasUnknownType($layerMetaData, $feature)) {
            throw new \Exception(
                'Importing geometry '.$feature['id'].' for layer '.$layerMetaData['layer_name'].
                ' with type '.$feature['properties_msp']['type'].', but this type has not been defined in the 
                session config file, so not continuing.'
            );
        }

        $geometryData = $feature["geometry"];
        // let's make sure we are always working with multidata: multipolygon, multilinestring, multipoint
        if ($geometryData["type"] == "Polygon"
            || $geometryData["type"] == "LineString"
            ||  $geometryData["type"] == "Point"
        ) {
            $geometryData["coordinates"] = [$geometryData["coordinates"]];
            $geometryData["type"] = "Multi".$geometryData["type"];
        }

        $encodedFeatureProperties = json_encode($feature["properties"]);
        if (strcasecmp($geometryData["type"], "MultiPolygon") == 0) {
            foreach ($geometryData["coordinates"] as $multi) {
                if (!is_array($multi)) {
                    continue;
                }
                $returnChecks[] = $this->addMultiPolygon(
                    $multi,
                    $layerId,
                    $encodedFeatureProperties,
                    $feature['properties_msp']['countryId'],
                    $feature['properties_msp']['type'],
                    $feature['properties_msp']['mspId'],
                    $layerMetaData['layer_name']
                );
            }
            return (!array_search(false, $returnChecks ?? [], true));
        }
        if (strcasecmp($geometryData["type"], "MultiPoint") == 0) {
            return (!is_null($this->addGeometry(
                [
                    'geometry_layer_id' => $layerId,
                    'geometry_geometry' => json_encode($geometryData["coordinates"]),
                    'geometry_data' => $encodedFeatureProperties,
                    'geometry_country_id' => $feature['properties_msp']['countryId'],
                    'geometry_type' => $feature['properties_msp']['type'],
                    'geometry_mspid' => $feature['properties_msp']['mspId'],
                    'geometry_subtractive' => 0
                ],
                $layerMetaData['layer_name']
            )));
        }
        if (strcasecmp($geometryData["type"], "MultiLineString") == 0) {
            foreach ($geometryData["coordinates"] as $line) {
                $returnChecks2[] = $this->addGeometry(
                    [
                        'geometry_layer_id' => $layerId,
                        'geometry_geometry' => json_encode($line),
                        'geometry_data' => $encodedFeatureProperties,
                        'geometry_country_id' => $feature['properties_msp']['countryId'],
                        'geometry_type' => $feature['properties_msp']['type'],
                        'geometry_mspid' => $feature['properties_msp']['mspId'],
                        'geometry_subtractive' => 0
                    ],
                    $layerMetaData['layer_name']
                );
            }
            return (!array_search(null, $returnChecks2 ?? [], true));
        }
        return false;
    }

    public function moveDataFromArray(
        array $layerMetaData,
        array $feature
    ): array {
        $featureProperties = $feature['properties'];
        if (!empty($layerMetaData["layer_property_as_type"])) {
            // check if the layer_property_as_type value exists in $featureProperties
            $type = '-1';
            if (!empty($featureProperties[$layerMetaData["layer_property_as_type"]])) {
                $featureTypeProperty = $featureProperties[$layerMetaData["layer_property_as_type"]];
                foreach ($layerMetaData["layer_type"] as $layerTypeMetaData) {
                    if (!empty($layerTypeMetaData["map_type"])) {
                        // identify the 'other' category
                        if (strtolower($layerTypeMetaData["map_type"]) == "other") {
                            $typeOther = $layerTypeMetaData["value"];
                        }
                        // translate the found $featureProperties value to the type value
                        if ($layerTypeMetaData["map_type"] == $featureTypeProperty) {
                            $type = $layerTypeMetaData["value"];
                            break;
                        }
                    }
                }
            }
            if ($type == -1) {
                $type = $typeOther ?? 0;
            }
        } else {
            $type = (int)($featureProperties['type'] ?? 0);
            unset($featureProperties['type']);
        }

        if (isset($featureProperties['mspid'])
            && is_numeric($featureProperties['mspid'])
            && intval($featureProperties['mspid']) !== 0
        ) {
            $mspId = intval($featureProperties['mspid']);
            unset($featureProperties['mspid']);
        }

        if (isset($featureProperties['country_id'])
            && is_numeric($featureProperties['country_id'])
            && intval($featureProperties['country_id']) !== 0
        ) {
            $countryId = intval($featureProperties['country_id']);
            unset($featureProperties['country_id']);
        }

        $feature['properties'] = $featureProperties;
        $feature['properties_msp']['type'] = $type;
        $feature['properties_msp']['mspId'] = $mspId ?? null;
        $feature['properties_msp']['countryId'] = $countryId ?? null;
        return $feature;
    }

    public function featureHasUnknownType(array $layerMetaData, array $feature): bool
    {
        return (!isset($layerMetaData['layer_type'][$feature['properties_msp']['type']]));
    }

    private function addMultiPolygon(
        array $multi,
        int $layerId,
        string $jsonData,
        ?int $countryId,
        string $type,
        ?int $mspId,
        string $layerName
    ): bool {
        $lastId = 0;
        for ($j = 0; $j < sizeof($multi); $j++) {
            if (sizeof($multi) > 1 && $j != 0) {
                //this is a subtractive polygon
                $this->addGeometry(
                    [
                        'geometry_layer_id' => $layerId,
                        'geometry_geometry' => json_encode($multi[$j]),
                        'geometry_data' => $jsonData,
                        'geometry_country_id' => $countryId,
                        'geometry_type' => $type,
                        'geometry_mspid' => null,
                        'geometry_subtractive' => $lastId
                    ],
                    $layerName
                );
            } else {
                $lastId = $this->addGeometry(
                    [
                        'geometry_layer_id' => $layerId,
                        'geometry_geometry' => json_encode($multi[$j]),
                        'geometry_data' => $jsonData,
                        'geometry_country_id' => $countryId,
                        'geometry_type' => $type,
                        'geometry_mspid' => $mspId,
                        'geometry_subtractive' => 0
                    ],
                    $layerName
                );
            }
        }
        return false;
    }

    private function addGeometry(array $geometryColumns, $layerName = ''): int|null
    {
        if (empty($geometryColumns['geometry_geometry'])
            || empty($geometryColumns['geometry_layer_id'])
        ) {
            throw new \Exception('Need at least some geometry and a layer ID to continue.');
        }
        $subtractive = $geometryColumns['geometry_subtractive'] ?? 0;
        if ($subtractive === 0 && empty($geometryColumns['geometry_mspid'])) {
            // so many algorithms to choose from, but this one seemed to have low collision, reasonable speed,
            //   and simply availability to PHP in default installation
            $algo = 'fnv1a64';
            // to avoid duplicate MSP IDs, we need the string to include the layer name, the geometry, and if available
            //   the geometry's name ... there have been cases in which one layer had exactly the same geometry twice
            //   to indicate two different names given to that area... very annoying
            $dataToHash = $layerName.$geometryColumns['geometry_geometry'];
            $dataArray = json_decode($geometryColumns['geometry_data'], true);
            $dataToHash .= $dataArray['name'] ?? '';
            $geometryColumns['geometry_mspid'] = hash($algo, $dataToHash);
        }
        return $this->insert('geometry', $geometryColumns);
    }

    /**
     * Returns the database id of the persistent geometry id described by the base_geometry_info
     *
     */
    public function fixupPersistentGeometryID(array $baseGeometryInfo, array $mappedGeometryIds): int|string
    {
        $fixedGeometryId = -1;
        if (!empty($baseGeometryInfo["geometry_mspid"])) {
            $fixedGeometryId = $this->getGeometryIdByMspId($baseGeometryInfo["geometry_mspid"]);
        } else {
            if (array_key_exists($baseGeometryInfo["geometry_persistent"], $mappedGeometryIds)) {
                $fixedGeometryId = $mappedGeometryIds[$baseGeometryInfo["geometry_persistent"]];
            } else {
                $return = "Found geometry ID (Fallback field \"geometry_persistent\": ".
                    $baseGeometryInfo["geometry_persistent"].
                    ") which is not referenced by msp id and hasn't been imported by the plans importer yet. ".
                    var_export($baseGeometryInfo, true);
            }
        }
        return $return ?? $fixedGeometryId;
    }

    /**
     * Returns the database id of the geometry id described by the base_geometry_info
     *
     */
    public function fixupGeometryID(array $baseGeometryInfo, array $mappedGeometryIds): int|string
    {
        $fixedGeometryId = -1;
        if (array_key_exists($baseGeometryInfo["geometry_id"], $mappedGeometryIds)) {
            $fixedGeometryId = $mappedGeometryIds[$baseGeometryInfo["geometry_id"]];
        } else {
            // If we can't find the geometry id in the ones that we already have imported, check if the geometry id
            //   matches the persistent id, and if so select it by the mspid since this should all be present then.
            if ($baseGeometryInfo["geometry_id"] == $baseGeometryInfo["geometry_persistent"]) {
                if (isset($baseGeometryInfo["geometry_mspid"])) {
                    $fixedGeometryId = $this->getGeometryIdByMspId($baseGeometryInfo["geometry_mspid"]);
                } else {
                    $return = "Found geometry (".implode(", ", $baseGeometryInfo).
                        " which has not been imported by the plans importer. The persistent id matches but mspid is".
                        "not set.";
                }
            } else {
                $return = "Found geometry ID (Fallback field \"geometry_id\": ". $baseGeometryInfo["geometry_id"].
                    ") which hasn't been imported by the plans importer yet.";
            }
        }
        return $return ?? $fixedGeometryId;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function getGeometryIdByMspId(int|string $mspId): int|string
    {
        $return = $this->selectRowsFromTable('geometry', ['geometry_mspid' => $mspId])['geometry_id'];
        if (is_null($return)) {
            return 'Could not find MSP ID ' . $mspId . ' in the current database';
        }
        return (int) $return;
    }

    /**
     * @throws \Exception
     */
    private function setupLayerMeta(): void
    {
        foreach ($this->dataModel['meta'] as $layerMetaData) {
            $this->layer->setupMetaForLayer($layerMetaData);
        }
        $this->gameSessionLogger->info(
            'Layer metadata set up.',
            ['gameSession' => $this->gameSession->getId()]
        );
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
        }
        $setupReturn = $this->plan->setupRestrictions($this->dataModel);
        if (is_array($setupReturn)) {
            foreach ($setupReturn as $returnedWarning) {
                $this->gameSessionLogger->warning(
                    $returnedWarning,
                    ['gameSession' => $this->gameSession->getId()]
                );
            }
        }
        $this->gameSessionLogger->info(
            'Layer restrictions set up.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    private function setupSimulations(): void
    {
        $nameSpaceName = (new \ReflectionClass(Simulations::class))->getNamespaceName();
        $simulationsDone = [];
        $possibleSims = array_keys($this->provider->getComponentsVersions());
        foreach ($possibleSims as $possibleSim) {
            if (array_key_exists($possibleSim, $this->dataModel)
                && is_array($this->dataModel[$possibleSim])
                && class_exists("{$nameSpaceName}\\{$possibleSim}")
                && method_exists("{$nameSpaceName}\\{$possibleSim}", 'onSessionSetup')
                && method_exists("{$nameSpaceName}\\{$possibleSim}", 'setGameSessionId')
            ) {
                $this->gameSessionLogger->info(
                    'Setting up simulation {simulation}...',
                    ['simulation' => $possibleSim, 'gameSession' => $this->gameSession->getId()]
                );
                $simulation = new ("{$nameSpaceName}\\{$possibleSim}")();
                $simulation->setGameSessionId($this->gameSession->getId());
                $return = $simulation->onSessionSetup($this->dataModel);
                if (is_array($return)) {
                    foreach ($return as $message) {
                        $this->gameSessionLogger->warning(
                            '{simulation} returned the message: {message}',
                            [
                                'simulation' => $possibleSim,
                                'message' => $message,
                                'gameSession' => $this->gameSession->getId()
                            ]
                        );
                    }
                }
                $this->gameSessionLogger->info(
                    'Finished setting up simulation {simulation}.',
                    ['simulation' => $possibleSim, 'gameSession' => $this->gameSession->getId()]
                );
                $simulationsDone[] = $possibleSim;
            }
        }
        $remainingSims = array_diff($possibleSims, $simulationsDone);
        foreach ($remainingSims as $remainingSim) {
            if (key_exists($remainingSim, $this->dataModel)) {
                $this->gameSessionLogger->error(
                    'Unable to set up {simulation}.',
                    ['simulation' => $remainingSim, 'gameSession' => $this->gameSession->getId()]
                );
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function setupPlans(): void
    {
        $return = $this->plan->setupPlans($this->dataModel);
        if (is_array($return)) {
            foreach ($return as $message) {
                $this->gameSessionLogger->warning(
                    'Plan setup returned the message: {message}',
                    ['message' => $message, 'gameSession' => $this->gameSession->getId()]
                );
            }
        } else {
            $this->gameSessionLogger->info(
                'Plan setup was successful',
                ['gameSession' => $this->gameSession->getId()]
            );
        }
    }

    private function setupObjectives(): void
    {
        $this->objective->setupObjectives($this->dataModel);
    }

    /**
     * @throws \Exception
     */
    private function setupGameWatchdogAndAccess(): void
    {
        // get the watchdog and end-user log-on in order
        $qb = $this->connection->createQueryBuilder();
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
            if (null !== $promise = $this->game->changeWatchdogState("SETUP")) {
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
        $completeConfig = $this->gameSession->getGameConfigVersion()->getGameConfigComplete();
        // https://github.com/justinrainbow/json-schema !!
        $this->dataModel = $completeConfig['datamodel'] ?? throw new \Exception('nope.');
    }

    /**
     * @throws Exception
     */
    private function insert(string $table, array $values): int
    {
        $qb = $this->connection->createQueryBuilder();
        foreach ($values as $key => $value) {
            $values[$key] = $qb->createPositionalParameter($value);
        }
        $qb->insert($table)
            ->values($values)
            ->executeStatement();
        return $this->connection->lastInsertId('auto_increment');
    }
}
