<?php

namespace App\MessageHandler\GameList;

use App\Domain\API\v1\Game;
use App\Domain\API\v1\Geometry;
use App\Domain\API\v1\Layer;
use App\Domain\API\v1\Objective;
use App\Domain\API\v1\Plan;
use App\Domain\API\v1\Simulations;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Communicator\GeoServerCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use App\Message\GameList\GameListCreationMessage;
use App\Repository\ServerManager\GameListRepository;
use App\VersionsProvider;
use Doctrine\DBAL\Connection;
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
    private Connection $connection;

    private GameList $gameSession;

    private array $dataModel;

    /*private Game $game;
    private Layer $layer;
    private Geometry $geometry;
    private Objective $objective;
    private Plan $plan;*/

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
        $this->connection = $connectionManager->getCachedGameSessionDbConnection($this->gameSession->getId());
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
        $eraRealtimeString = str_repeat(($this->dataModel['era_planning_realtime'] ?? 300). ",", 4);
        $eraRealtimeString = substr($eraRealtimeString, 0, -1);
        $this->insert('game', [
                'game_start' => $this->dataModel['start'] ?? date('Y'),
                'game_planning_gametime' => $this->dataModel['era_planning_months'] ?? 1,
                'game_planning_realtime' => $this->dataModel['era_planning_realtime'] ?? 300,
                'game_planning_era_realtime' => $eraRealtimeString,
                'game_eratime' =>
                    max($this->dataModel['era_total_months'], $this->params->get('app.min_game_era_time')),
                'game_configfile' =>
                    sprintf($this->params->get('app.session_config_name'), $this->gameSession->getId()),
                'game_autosave_month_interval' => $this->params->get('app.game_auto_save_interval')
        ]);
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
        $countries = [
            [
                'country_id' => 1,
                'country_colour' => $this->dataModel['user_admin_color'] ?? '#FF00FFFF',
                'country_is_manager' => 1
            ],
            [
                'country_id' => 2,
                'country_colour' => $this->dataModel['user_region_manager_color'] ?? '#00FFFFFF',
                'country_is_manager' => 1
            ]
        ];
        foreach ($this->dataModel['meta'] as $layerMeta) {
            if ($layerMeta['layer_name'] == $this->dataModel['countries']) {
                foreach ($layerMeta['layer_type'] as $country) {
                    $countries[] = [
                        'country_id' => $country['value'],
                        'country_name' => $country['displayName'],
                        'country_colour' => $country['polygonColor'],
                        'country_is_manager' => 0
                    ];
                }
                break;
            }
        }
        foreach ($countries as $country) {
            $this->insert('game', $country);
        }
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
            $this->insert('layer', [
                'layer_name' => $layerMetaData['layer_name'],
                'layer_geotype' => $layerMetaData['layer_geotype'],
                'layer_group' => $this->dataModel['region'],
                'layer_editable' => 0,
                'layer_raster' => $rasterMetaData
            ]);
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
            $this->gameSessionLogger->info(
                'Successfully retrieved {layerName} and stored the raster file at {rasterFileName}.',
                [
                    'gameSession' => $this->gameSession->getId(),
                    'layerName' => $layerMetaData['layer_name'],
                    'rasterFileName' => $rasterFileName
                ]
            );
            return;
        }
        // Create the metadata for the raster layer, but don't fill in the layer_raster field.
        $this->insert('layer', [
            'layer_name' => $layerMetaData['layer_name'],
            'layer_geotype' => $layerMetaData['layer_geotype'],
            'layer_group' => $this->dataModel['region'],
            'layer_editable' => 0
        ]);
        $this->gameSessionLogger->info(
            'Successfully retrieved {layerName} without storing a raster file, as requested.',
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
        if (!array_key_exists('layer_download_from_geoserver', $layerMetaData) ||
            $layerMetaData['layer_download_from_geoserver']
        ) {
            $qb = $this->connection->createQueryBuilder();
            $result = $qb->select('l.layer_id')
                ->from('layer', 'l')
                ->where($qb->expr()->eq('l.layer_name', $layerMetaData['layer_name']))
                ->executeQuery()
                ->fetchOne();
            if ($result === false) {
                $layerId = $this->insert('layer', [
                    'layer_name' => $layerMetaData['layer_name'],
                    'layer_geotype' => $layerMetaData['layer_geotype'],
                    'layer_group' => $this->dataModel['region']
                ]);
            } else {
                $layerId = $result['layer_id'];
            }
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
                    if (!$this->geometry->processAndAdd($feature, $layerId, $layerMetaData)) {
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
            $this->gameSessionLogger->info(
                'Successfully retrieved {layerName} and stored the geometry in the database.',
                ['gameSession' => $this->gameSession->getId(), 'layerName' => $layerMetaData['layer_name']]
            );
            return;
        }
        // Create the metadata for the vector layer, but don't fill the geometry table.
        $this->insert('layer', [
            'layer_name' => $layerMetaData['layer_name'],
            'layer_geotype' => $layerMetaData['layer_geotype'],
            'layer_group' => $this->dataModel['region']
        ]);
        $this->gameSessionLogger->info(
            'Successfully retrieved {layerName} without storing geometry in the database, as requested.',
            ['gameSession' => $this->gameSession->getId(), 'layerName' => $layerMetaData['layer_name']]
        );
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
