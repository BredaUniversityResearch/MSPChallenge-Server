<?php

namespace App\MessageHandler\GameList;

use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Log\LogContainerInterface;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SimulationHelper;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Entity\Watchdog;
use App\Logger\GameSessionLogger;
use App\Message\GameList\GameListArchiveMessage;
use App\Message\GameList\GameListCreationMessage;
use App\Message\GameSave\GameSaveCreationMessage;
use App\Message\GameSave\GameSaveLoadMessage;
use App\Repository\WatchdogRepository;
use App\VersionsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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

    /**
     * @throws Exception
     */
    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $gameSessionLogger,
        ConnectionManager $connectionManager,
        ContainerBagInterface $params,
        GameSessionLogger $gameSessionLogFileHandler,
        WatchdogCommunicator $watchdogCommunicator,
        VersionsProvider $provider,
        private readonly SimulationHelper $simulationHelper
    ) {
        parent::__construct($gameSessionLogger);
        $this->kernel = $kernel;
        $this->mspServerManagerEntityManager = $connectionManager->getServerManagerEntityManager();
        $this->connectionManager = $connectionManager;
        $this->params = $params;
        $this->gameSessionLogFileHandler = $gameSessionLogFileHandler;
        $this->watchdogCommunicator = $watchdogCommunicator;
        $this->normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        $this->provider = $provider;
    }

    /**
     * @throws Exception
     */
    protected function setGameSessionAndDatabase(
        GameSaveLoadMessage|GameListCreationMessage|GameSaveCreationMessage|GameListArchiveMessage $gameList
    ): void {
        $this->gameSession = $this->mspServerManagerEntityManager->getRepository(GameList::class)->find($gameList->id)
            ?? throw new Exception('Game session not found, so cannot continue.');
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
        $process->setTimeout(null); // Disable the process timeout
        $process->mustRun(fn($type, $buffer) => $this->info($buffer));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
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
     * @throws Exception
     */
    protected function validateGameConfig(string $gameConfigFilepath): void
    {
        if (false === $gameConfigContent = file_get_contents($gameConfigFilepath)) {
            throw new Exception(
                "Cannot read contents of the session's chosen configuration file: {$gameConfigFilepath}"
            );
        }
        $gameConfigContents = json_decode($gameConfigContent);
        if ($gameConfigContents === false) {
            throw new Exception(
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
            throw new Exception('Session config file invalid, so not continuing.');
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
     * @throws Exception
     */
    protected function registerSimulations(): void
    {
        /** @var WatchdogRepository $watchdogRepo */
        $watchdogRepo = $this->entityManager->getRepository(Watchdog::class);
        $watchdogRepo->registerSimulations($this->simulationHelper->getInternalSims(
            $this->gameSession->getId(),
            $this->dataModel
        ));
        $this->logContainer($watchdogRepo);
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
