<?php

namespace App\MessageHandler\GameList;

use App\Domain\Common\EntityEnums\WatchdogStatus;
use App\Domain\Common\InternalSimulationName;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Log\LogContainerInterface;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Entity\Simulation;
use App\Entity\Watchdog;
use App\Logger\GameSessionLogger;
use App\Message\GameList\GameListArchiveMessage;
use App\Message\GameList\GameListCreationMessage;
use App\Message\GameSave\GameSaveCreationMessage;
use App\Message\GameSave\GameSaveLoadMessage;
use App\Repository\WatchdogRepository;
use App\VersionsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;

abstract class CommonSessionHandlerBase extends SessionLogHandlerBase
{
    protected ObjectNormalizer $normalizer;
    protected ?string $phpBinary = null;

    protected string $database;

    protected KernelInterface $kernel;

    protected GameList $gameSession;

    protected EntityManagerInterface $entityManager;

    protected EntityManagerInterface $mspServerManagerEntityManager;

    protected readonly ConnectionManager $connectionManager;

    protected readonly ContainerBagInterface $params;

    protected GameSessionLogger $gameSessionLogFileHandler;

    protected WatchdogCommunicator $watchdogCommunicator;

    protected \ZipArchive $saveZip;

    protected GameSave $gameSave;

    protected array $dataModel;

    protected readonly VersionsProvider $provider;

    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $gameSessionLogger,
        EntityManagerInterface $mspServerManagerEntityManager,
        ConnectionManager $connectionManager,
        ContainerBagInterface $params,
        GameSessionLogger $gameSessionLogFileHandler,
        WatchdogCommunicator $watchdogCommunicator,
        VersionsProvider $provider
    ) {
        parent::__construct($gameSessionLogger);
        $this->kernel = $kernel;
        $this->mspServerManagerEntityManager = $mspServerManagerEntityManager;
        $this->connectionManager = $connectionManager;
        $this->params = $params;
        $this->gameSessionLogFileHandler = $gameSessionLogFileHandler;
        $this->watchdogCommunicator = $watchdogCommunicator;
        $this->normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        $this->provider = $provider;
    }

    /**
     * @throws \Exception
     */
    protected function setGameSessionAndDatabase(
        GameSaveLoadMessage|GameListCreationMessage|GameSaveCreationMessage|GameListArchiveMessage $gameList
    ): void {
        $this->gameSession = $this->mspServerManagerEntityManager->getRepository(GameList::class)->find($gameList->id)
            ?? throw new \Exception('Game session not found, so cannot continue.');
        $sessionId = $this->gameSession->getId();
        $this->setGameSessionId($sessionId);
        $this->database = $this->connectionManager->getGameSessionDbName($sessionId);
        $this->entityManager = $this->connectionManager->getGameSessionEntityManager($sessionId);
    }

    protected function dropSessionDatabase(): void
    {
        $this->phpBinary ??= (new PhpExecutableFinder)->find(false);
        $process = new Process([
            $this->phpBinary,
            'bin/console',
            'doctrine:database:drop',
            '--connection='.$this->database,
            '--force',
            '--if-exists',
            '--no-interaction',
            '--env='.$_ENV['APP_ENV']
        ], $this->kernel->getProjectDir());
        $process->mustRun(fn($type, $buffer) => $this->info($buffer));
    }

    protected function exportSessionDatabase(): string
    {
        $mysqlBinary = (new ExecutableFinder)->find('mysqldump');
        $process = new Process([
            $mysqlBinary,
            '--host='.$_ENV['DATABASE_HOST'],
            '--port='.$_ENV['DATABASE_PORT'],
            '--user='.$_ENV['DATABASE_USER'],
            '--password='.$_ENV['DATABASE_PASSWORD'],
            ($_ENV['APP_ENV'] == 'test') ? $this->database.'_test' : $this->database,
        ], $this->kernel->getProjectDir());
        $process->run();
        return $process->getOutput();
    }

    protected function resetSessionDatabase(): void
    {
        $this->dropSessionDatabase();
        $this->createSessionDatabase();
    }

    protected function createSessionDatabase(): void
    {
        $this->phpBinary ??= (new PhpExecutableFinder)->find(false);
        $process = new Process([
            $this->phpBinary,
            'bin/console',
            'doctrine:database:create',
            '--connection='.$this->database,
            '--no-interaction',
            '--env='.$_ENV['APP_ENV']
        ], $this->kernel->getProjectDir());
        $process->mustRun(fn($type, $buffer) => $this->info($buffer));
    }

    protected function migrateSessionDatabase(): void
    {
        $this->phpBinary ??= (new PhpExecutableFinder)->find(false);
        $process = new Process([
            $this->phpBinary,
            'bin/console',
            'doctrine:migrations:migrate',
            '-vv',
            '--em='.$this->database,
            '--no-interaction',
            '--env='.$_ENV['APP_ENV']
        ], $this->kernel->getProjectDir());
        $process->mustRun(fn($type, $buffer) => $this->info($buffer));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Exception
     */
    protected function resetSessionRasterStore(): void
    {
        $this->info("Resetting the session raster store...");
        $this->removeSessionRasterStore();
        $sessionRasterStore = $this->params->get('app.session_raster_dir').$this->gameSession->getId();
        $fileSystem = new Filesystem();
        $dirs = [$sessionRasterStore, $sessionRasterStore . '/archive'];
        $fileSystem->mkdir($dirs);
        $fileSystem->chmod($dirs, 0777); // umask issues in prod can prevent mkdir to create with default 0777
        $this->info("Reset the session raster store at {$sessionRasterStore}");
    }

    protected function removeSessionRasterStore(): void
    {
        $sessionRasterStore = $this->params->get('app.session_raster_dir').$this->gameSession->getId();
        $fileSystem = new Filesystem();
        if ($fileSystem->exists($sessionRasterStore)) {
            $finder = new Finder();
            if ($fileSystem->exists($sessionRasterStore . '/archive')) {
                $finder->files()->in($sessionRasterStore . '/archive');
                if ($finder->hasResults()) {
                    $fileSystem->remove($finder->files()->getIterator());
                }
            }
            $finder->files()->in($sessionRasterStore);
            if ($finder->hasResults()) {
                $fileSystem->remove($finder->files()->getIterator());
            }
            $fileSystem->remove($sessionRasterStore);
        }
    }

    /**
     * @throws \Exception
     */
    protected function validateGameConfig(string $gameConfigFilepath): void
    {
        if (false === $gameConfigContent = file_get_contents($gameConfigFilepath)) {
            throw new \Exception(
                "Cannot read contents of the session's chosen configuration file: {$gameConfigFilepath}"
            );
        }
        $gameConfigContents = json_decode($gameConfigContent);
        if ($gameConfigContents === false) {
            throw new \Exception(
                "Cannot decode contents of the session's chosen configuration file: {$gameConfigFilepath}"
            );
        }
        $schema = Schema::import(json_decode(
            file_get_contents($this->kernel->getProjectDir().'/src/Domain/SessionConfigJSONSchema.json')
        ));
        try {
            $schema->in($gameConfigContents);
        } catch (InvalidValue $e) {
            $this->error(
                "Session config file {$gameConfigFilepath} failed to pass validation, having meta data: ".
                    json_encode($gameConfigContents->metadata)
            );
            $this->error($e->getMessage());
            throw new \Exception('Session config file invalid, so not continuing.');
        }
        $this->info(
            "Contents of config file {$gameConfigFilepath} were successfully validated, having meta data: ".
            json_encode($gameConfigContents->metadata)
        );
        $gameConfigContents = json_decode($gameConfigContent, true); // to array
        // todo: we should just use the object version instead of the array one.
        $this->dataModel = $gameConfigContents['datamodel'];
    }

    /**
     * @throws \Exception
     */
    protected function registerSimulations(): void
    {
        // filter possible internal simulations with the ones present in the config
        $simNames = array_keys(array_intersect_key(
            array_flip(array_map(
                fn(InternalSimulationName $e) => $e->value,
                InternalSimulationName::cases()
            )),
            $this->dataModel
        ));
        if (empty($simNames)) {
            $this->warning('No simulations found to register in game configuration');
            return; // no configured simulations
        }

        $versions = $this->getConfiguredSimulationTypes();
        /** @var WatchdogRepository $watchdogRepo */
        $watchdogRepo = $this->entityManager->getRepository(Watchdog::class);
        if (null === $watchdog = $watchdogRepo->findOneBy(['serverId' => Watchdog::getInternalServerId()])) {
            $watchdog = new Watchdog();
            $watchdog
                ->setAddress(
                    $_ENV['WATCHDOG_ADDRESS'] ?? $this->gameSession->getGameWatchdogServer()->getAddress() ??
                        'localhost'
                )
                ->setPort((int)($_ENV['WATCHDOG_PORT'] ?? 45000))
                ->setToken(0) // this is temporary and will be updated later
                ->setStatus(WatchdogStatus::READY)
                ->setServerId(Watchdog::getInternalServerId());
            $this->entityManager->persist($watchdog);
            $this->entityManager->flush();

            // update watchdog record with token using DQL
            $qb = $this->entityManager->getRepository(Watchdog::class)->createQueryBuilder('w');
            $qb
                ->update()
                ->set('w.token', 'UUID_SHORT()')
                ->where($qb->expr()->eq('w.serverId', ':serverId'))
                ->setParameter('serverId', Watchdog::getInternalServerId()->toBinary())
                ->getQuery()
                ->execute();
        }
        $simulations = collect($watchdog->getSimulations()->toArray())->keyBy(fn(Simulation $s) => $s->getName())
            ->all();
        foreach ($simNames as $simName) {
            if (array_key_exists($simName, $simulations)) {
                $this->info("Simulation {$simName} already registered, skipping.");
                continue;
            }
            $sim = new Simulation();
            $sim->setName($simName);
            $sim->setVersion($versions[$simName]);
            $sim->setWatchdog($watchdog);
            $this->entityManager->persist($sim);
        }
        $this->entityManager->flush();
    }

    private function getConfiguredSimulationTypes(): array
    {
        $result = array();
        $possibleSims = $this->provider->getComponentsVersions();
        foreach ($possibleSims as $possibleSim => $possibleSimVersion) {
            if (array_key_exists($possibleSim, $this->dataModel) && is_array($this->dataModel[$possibleSim])) {
                $versionString = $possibleSimVersion;
                if (array_key_exists("force_version", $this->dataModel[$possibleSim])) {
                    $versionString = $this->dataModel[$possibleSim]["force_version"];
                }
                $result[$possibleSim] = $versionString;
            }
        }
        return $result;
    }

    protected function logContainer(LogContainerInterface $container): void
    {
        $logs = $container->getLogs();
        foreach ($logs as $log) {
            switch ($log[LogContainerInterface::LOG_FIELD_LEVEL]) {
                case LogContainerInterface::LOG_LEVEL_DEBUG:
                    $this->debug($log[LogContainerInterface::LOG_FIELD_MESSAGE]);
                    break;
                case LogContainerInterface::LOG_LEVEL_WARNING:
                    $this->warning($log[LogContainerInterface::LOG_FIELD_MESSAGE]);
                    break;
                case LogContainerInterface::LOG_LEVEL_ERROR:
                    $this->error($log[LogContainerInterface::LOG_FIELD_MESSAGE]);
                    break;
                case LogContainerInterface::LOG_LEVEL_INFO:
                default:
                    $this->info($log[LogContainerInterface::LOG_FIELD_MESSAGE]);
            }
        }
    }
}
