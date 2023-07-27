<?php
namespace App\MessageHandler;

use App\Domain\API\v1\Game;
use App\Domain\API\v1\Geometry;
use App\Domain\API\v1\Layer;
use App\Domain\API\v1\Objective;
use App\Domain\API\v1\Plan;
use App\Domain\API\v1\Security;
use App\Domain\API\v1\Simulations;
use App\Domain\API\v1\User;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Communicators\GeoServerCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use App\Repository\ServerManager\GameListRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function App\await;

#[AsMessageHandler]
class GameListHandler
{
    private readonly ConnectionManager $connectionManager;
    private string $rootToWrite;
    
    private GameList $gameSession;

    public function __construct(
        private readonly EntityManagerInterface $mspServerManagerEntityManager,
        private readonly GameListRepository $gameListRepository,
        private readonly LoggerInterface $gameSessionChannelLogger,
        private readonly HttpClientInterface $client,
        private readonly KernelInterface $kernel
    ) {
        $this->connectionManager = ConnectionManager::getInstance();
        $this->rootToWrite = ($_ENV['APP_ENV'] !== 'test') ?
            $this->kernel->getProjectDir() :
            $this->kernel->getLogDir();
    }

    public function __invoke(GameList $gameList): void
    {
        $this->gameSession = $this->gameListRepository->find($gameList->getId());
        if ((string) $this->gameSession->getSessionState() == 'request') {
            if (!is_null($this->gameSession->getGameConfigVersion())) {
                $this->setupSession();
                return;
            }
            if (!is_null($this->gameSession->getGameSave())) {
                $this->loadSession();
                return;
            }
            return;
        }
        if ((string) $this->gameSession->getSessionState() == 'archived') {
            $this->archiveSession();
            return;
        }
        if ((string) $this->gameSession->getSessionState() == 'healthy' &&
            (!is_null($this->gameSession->getGameSave()))) {
            $this->saveSession();
            return;
        }
    }

    private function saveSession(): void
    {
        return;
    }

    private function archiveSession(): void
    {
        return;
    }

    private function loadSession(): void
    {
        // will need to use the mysql command to load the dumped SQL
        // as doctrine dropped support for doing it through doctrine:database:import on the CLI
        // and also through doctrine:query:sql it didn't work when I tested it
        return;
    }

    private function setupSession(): void
    {
        try {
            $this->gameSessionChannelLogger->notice(
                'Session {name} creation initiated. This might take a while.',
                ['name' => $this->gameSession->getName(), 'gameSession' => $this->gameSession->getId()]
            );
            $this->createSessionDatabase();
            $this->migrateSessionDatabase();
            $this->resetSessionRasterStore();
            $this->createSessionRunningConfig();
            $this->finaliseSession();
            $this->gameSessionChannelLogger->notice(
                'Session {name} created and ready for use.',
                ['name' => $this->gameSession->getName(), 'gameSession' => $this->gameSession->getId()]
            );
        } catch (\Exception $e) {
            $this->gameSessionChannelLogger->error(
                'Session {name} failed to create. Try to resolve the problem and retry. Problem: {problem}',
                [
                    'name' => $this->gameSession->getName(),
                    'problem' => $e->getMessage(),
                    'gameSession' => $this->gameSession->getId()
                ]
            );
            $this->gameSession->setSessionState(new GameSessionStateValue('failed'));
            $this->mspServerManagerEntityManager->persist($this->gameSession);
            $this->mspServerManagerEntityManager->flush();
        }

        $this->gameSession->setSessionState(new GameSessionStateValue('healthy'));
        $this->mspServerManagerEntityManager->persist($this->gameSession);
        $this->mspServerManagerEntityManager->flush();
    }

    private function createSessionDatabase(): void
    {
        $conn = $this->connectionManager->getGameSessionDbName($this->gameSession->getId());

        $app = new Application($this->kernel);

        $input0 = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--force' => true,
            '--no-interaction' => true,
            '--if-exists' => true,
            '--connection' => $conn,
            '--env' => 'test'
        ]);
        $input0->setInteractive(false);
        $output0 = new BufferedOutput();
        $app->doRun($input0, $output0);
        $this->gameSessionChannelLogger->info(
            $output0->fetch(),
            ['gameSession' => $this->gameSession->getId()]
        );

        $input = new ArrayInput([
            'command' => 'doctrine:database:create',
            '--connection' => $conn,
            '--if-not-exists' => true,
            '--env' => 'test'
        ]);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $app->doRun($input, $output);
        $this->gameSessionChannelLogger->info(
            $output->fetch(),
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    private function migrateSessionDatabase(): void
    {
        $em = $this->connectionManager->getGameSessionDbName($this->gameSession->getId());

        $app = new Application($this->kernel);
        $input = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--em' => $em,
            '--env' => 'test'
        ]);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $app->doRun($input, $output);
        $this->gameSessionChannelLogger->info(
            $output->fetch(),
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    private function resetSessionRasterStore(): void
    {
        $sessionRasterStore = $this->rootToWrite.'/raster/'.$this->gameSession->getId();
        $fileSystem = new Filesystem();
        if ($fileSystem->exists($sessionRasterStore)) {
            $fileSystem->remove($sessionRasterStore);
        }
        $fileSystem->mkdir($sessionRasterStore);
        $fileSystem->mkdir($sessionRasterStore . '/archive');
        $this->gameSessionChannelLogger->info(
            'Reset the session raster store at {sessionRasterStore}',
            ['gameSession' => $this->gameSession->getId(), 'sessionRasterStore' => $sessionRasterStore]
        );
    }

    private function createSessionRunningConfig(): void
    {
        $sessionConfigStore = $this->rootToWrite.
            '/running_session_config/session_config_'.$this->gameSession->getId().'.json';
        $fileSystem = new Filesystem();
        $fileSystem->copy(
            $this->kernel->getProjectDir().
            '/ServerManager/configfiles/'.$this->gameSession->getGameConfigVersion()->getFilePath(),
            $sessionConfigStore
        );
        $this->gameSessionChannelLogger->info(
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
        $this->setupSecurityTokens();
        $this->importLayerData();
        $this->setupLayerMeta();
        $this->setupRestrictions();
        $this->setupSimulations();
        $this->setupObjectives();
        $this->setupPlans();
        $this->setupGameWatchdogAndAccess();
    }

    /**
     * @throws Exception
     */
    private function setupSecurityTokens(): void
    {
        $security = new Security();
        $security->setGameSessionId($this->gameSession->getId());
        $security->generateToken(
            Security::ACCESS_LEVEL_FLAG_REQUEST_TOKEN,
            Security::TOKEN_LIFETIME_INFINITE
        );
        $managerToken =
            $security->generateToken(
                Security::ACCESS_LEVEL_FLAG_SERVER_MANAGER,
                Security::TOKEN_LIFETIME_INFINITE
            )['token'] ?? 0;
        $this->gameSession->setApiAccessToken($managerToken);
        
        $this->gameSessionChannelLogger->info(
            'Security tokens set up.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    /**
     * @throws \Exception
     */
    private function setupGameCountries(): void
    {
        $dataModel = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel'] ?? [];
        $game = new Game();
        $game->setGameSessionId($this->gameSession->getId());
        $game->setupGameCountries($dataModel);
        $this->gameSessionChannelLogger->info(
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
        $dataModel = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel'] ?? [];
        $geoServerCommunicator = new GeoServerCommunicator($this->client);
        $geoServerCommunicator->setBasePath($this->gameSession->getGameGeoServer()->getAddress());
        $geoServerCommunicator->setUsername($this->gameSession->getGameGeoServer()->getUsername());
        $geoServerCommunicator->setPassword($this->gameSession->getGameGeoServer()->getPassword());

        foreach ($dataModel['meta'] as $layerMetaData) {
            $this->gameSessionChannelLogger->info(
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
            $this->gameSessionChannelLogger->info(
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
        $region = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel']['region'] ?? [];
        $layer = new Layer();
        $layer->setGameSessionId($this->gameSession->getId());
        $rasterFileName = $this->rootToWrite.'/raster/'.$this->gameSession->getId().'/'.
            $layerMetaData['layer_name'].
            '.png';
        if (!array_key_exists('layer_download_from_geoserver', $layerMetaData) ||
            $layerMetaData['layer_download_from_geoserver']
        ) {
            $rasterMetaData = $geoServerCommunicator->getRasterMetaData(
                $region,
                $layerMetaData['layer_name']
            );
            $layer->insertRowIntoTable(
                'layer',
                [
                    'layer_name' => $layerMetaData['layer_name'],
                    'layer_geotype' => $layerMetaData['layer_geotype'],
                    'layer_group' => $region,
                    'layer_editable' => 0,
                    'layer_raster' => $rasterMetaData
                ]
            );
            $rasterData = $geoServerCommunicator->getRasterDataThroughMetaData(
                $region,
                $layerMetaData,
                $rasterMetaData
            );
            $fileSystem = new Filesystem();
            $fileSystem->dumpFile(
                $rasterFileName,
                $rasterData
            );
            $this->gameSessionChannelLogger->info(
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
        $layer->insertRowIntoTable(
            'layer',
            [
                'layer_name' => $layerMetaData['layer_name'],
                'layer_geotype' => $layerMetaData['layer_geotype'],
                'layer_group' => $region,
                'layer_editable' => 0
            ]
        );
        $this->gameSessionChannelLogger->info(
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
        $region = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel']['region'] ?? [];
        $layer = new Layer();
        $layer->setGameSessionId($this->gameSession->getId());
        $geometry = new Geometry();
        $geometry->setGameSessionId($this->gameSession->getId());
        if (!array_key_exists('layer_download_from_geoserver', $layerMetaData) ||
            $layerMetaData['layer_download_from_geoserver']
        ) {
            $layerId = $layer->getIdOrAdd([
                'layer_name' => $layerMetaData['layer_name'],
                'layer_geotype' => $layerMetaData['layer_geotype'],
                'layer_group' => $region
            ]);
            $layersContainer = $geoServerCommunicator->getLayerDescription($region, $layerMetaData['layer_name']);
            foreach ($layersContainer as $layerWithin) {
                $geometryData = $geoServerCommunicator->getLayerGeometry($layerWithin['layerName']);
                $features = $geometryData['features']
                    ?? throw new \Exception(
                        'Geometry data call did not return a features variable, so something must be wrong.'
                    );
                foreach ($features as $feature) {
                    if (empty($feature["geometry"])) {
                        $this->gameSessionChannelLogger->error(
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
                    if (!$geometry->processAndAdd($feature, $layerId, $layerMetaData)) {
                        $this->gameSessionChannelLogger->error(
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
            $this->gameSessionChannelLogger->info(
                'Successfully retrieved {layerName} and stored the geometry in the database.',
                ['gameSession' => $this->gameSession->getId(), 'layerName' => $layerMetaData['layer_name']]
            );
            return;
        }
        // Create the metadata for the vector layer, but don't fill the geometry table.
            $layer->insertRowIntoTable(
                'layer',
                [
                    'layer_name' => $layerMetaData['layer_name'],
                    'layer_geotype' => $layerMetaData['layer_geotype'],
                    'layer_group' => $region
                ]
            ) ?? throw new \Exception('Unable to store retrieved layer information in the database.');
        $this->gameSessionChannelLogger->info(
            'Successfully retrieved {layerName} without storing geometry in the database, as requested.',
            ['gameSession' => $this->gameSession->getId(), 'layerName' => $layerMetaData['layer_name']]
        );
    }

    /**
     * @throws \Exception
     */
    private function setupLayerMeta(): void
    {
        $layers = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel']['meta'] ?? [];
        $layer = new Layer();
        $layer->setGameSessionId($this->gameSession->getId());
        if (empty($layers)) {
            $this->gameSessionChannelLogger->info(
                'No layer metadata to set up.',
                ['gameSession' => $this->gameSession->getId()]
            );
        }
        foreach ($layers as $layerMetaData) {
            $layer->setupMetaForLayer($layerMetaData);
        }
        $this->gameSessionChannelLogger->info(
            'Layer metadata set up.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    /**
     * @throws \Exception
     */
    private function setupRestrictions(): void
    {
        $dataModel = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel'] ?? [];
        $plan = new Plan();
        $plan->setGameSessionId($this->gameSession->getId());
        if (empty($dataModel['restrictions'])) {
            $this->gameSessionChannelLogger->info(
                'No layer restrictions to set up.',
                ['gameSession' => $this->gameSession->getId()]
            );
        }
        $setupReturn = $plan->setupRestrictions($dataModel);
        if (is_array($setupReturn)) {
            foreach ($setupReturn as $returnedWarning) {
                $this->gameSessionChannelLogger->warning(
                    $returnedWarning,
                    ['gameSession' => $this->gameSession->getId()]
                );
            }
        }
        $this->gameSessionChannelLogger->info(
            'Layer restrictions set up.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }

    /**
     * @throws \Exception
     */
    private function setupGame(): void
    {
        $dataModel = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel'] ?? [];
        $game = new Game();
        $game->setGameSessionId($this->gameSession->getId());
        $game->setupGame($dataModel);
        $this->gameSessionChannelLogger->info(
            'Basic game parameters set up.',
            ['gameSession' => $this->gameSession->getId()]
        );
    }
    private function setupSimulations(): void
    {
        $dataModel = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel'] ?? [];
        $nameSpaceName = (new \ReflectionClass(Simulations::class))->getNamespaceName();
        $simulationsDone = [];
        foreach (Simulations::POSSIBLE_SIMULATIONS as $possibleSim) {
            if (array_key_exists($possibleSim, $dataModel)
                && is_array($dataModel[$possibleSim])
                && class_exists($nameSpaceName.'\\'.$possibleSim)
                && method_exists($nameSpaceName.'\\'.$possibleSim, 'onSessionSetup')
                && method_exists($nameSpaceName.'\\'.$possibleSim, 'setGameSessionId')
            ) {
                $this->gameSessionChannelLogger->info(
                    'Setting up simulation {simulation}...',
                    ['simulation' => $possibleSim, 'gameSession' => $this->gameSession->getId()]
                );
                $simulation = new ($nameSpaceName.'\\'.$possibleSim)();
                $simulation->setGameSessionId($this->gameSession->getId());
                $return = $simulation->onSessionSetup($dataModel);
                if (is_array($return)) {
                    foreach ($return as $message) {
                        $this->gameSessionChannelLogger->warning(
                            '{simulation} returned the message: {message}',
                            [
                                'simulation' => $possibleSim,
                                'message' => $message,
                                'gameSession' => $this->gameSession->getId()
                            ]
                        );
                    }
                }
                $this->gameSessionChannelLogger->info(
                    'Finished setting up simulation {simulation}.',
                    ['simulation' => $possibleSim, 'gameSession' => $this->gameSession->getId()]
                );
                $simulationsDone[] = $possibleSim;
            }
        }
        $remainingSims = array_diff(Simulations::POSSIBLE_SIMULATIONS, $simulationsDone);
        foreach ($remainingSims as $remainingSim) {
            if (key_exists($remainingSim, $dataModel)) {
                $this->gameSessionChannelLogger->error(
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
        $dataModel = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel'] ?? [];
        $plan = new Plan();
        $plan->setGameSessionId($this->gameSession->getId());
        $return = $plan->setupPlans($dataModel);
        if (is_array($return)) {
            foreach ($return as $message) {
                $this->gameSessionChannelLogger->warning(
                    'Plan setup returned the message: {message}',
                    ['message' => $message, 'gameSession' => $this->gameSession->getId()]
                );
            }
        } else {
            $this->gameSessionChannelLogger->info(
                'Plan setup was successful',
                ['gameSession' => $this->gameSession->getId()]
            );
        }
    }

    private function setupObjectives(): void
    {
        $dataModel = $this->gameSession->getGameConfigVersion()->getGameConfigComplete()['datamodel'] ?? [];
        $objective = new Objective();
        $objective->setGameSessionId($this->gameSession->getId());
        $objective->setupObjectives($dataModel);
    }

    /**
     * @throws \Exception
     */
    private function setupGameWatchdogAndAccess(): void
    {
        // get the watchdog and end-user log-on in order
        $game = new Game();
        $game->setGameSessionId($this->gameSession->getId());
            $game->insertRowIntoTable(
                'game_session',
                [
                    'game_session_watchdog_address' => $this->gameSession->getGameWatchdogServer()->getAddress(),
                    'game_session_watchdog_token' => 'UUID_SHORT()',
                    'game_session_password_admin' => $this->gameSession->getPasswordAdmin(),
                    'game_session_password_player' => $this->gameSession->getPasswordPlayer() ?? ''
                ]
            ) ?? throw new \Exception('Unable to store Watchdog address and user access passwords.');
        if ($_ENV['APP_ENV'] !== 'test') {
            //Notify the simulation that the game has been set up so we start the simulations.
            //This is needed because MEL needs to be run before the game to setup the initial fishing values.
            //$this->asyncDataTransferTo($game);
            if (null !== $promise = $game->changeWatchdogState("SETUP")) {
                await($promise);
                $this->gameSessionChannelLogger->info(
                    'Watchdog and user access set up successfully.',
                    ['gameSession' => $this->gameSession->getId()]
                );
                return;
            };
            throw new \Exception('Watchdog failed to start up.');
        } else {
            $this->gameSessionChannelLogger->info(
                'User access set up successfully, but Watchdog was not started as you are in test mode.',
                ['gameSession' => $this->gameSession->getId()]
            );
        }
    }
}
