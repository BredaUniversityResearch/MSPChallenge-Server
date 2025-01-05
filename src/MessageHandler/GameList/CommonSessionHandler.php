<?php

namespace App\MessageHandler\GameList;

use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use App\Logger\GameSessionLogger;
use App\Message\GameList\GameListArchiveMessage;
use App\Message\GameList\GameListCreationMessage;
use App\Message\GameSave\GameSaveCreationMessage;
use App\Message\GameSave\GameSaveLoadMessage;
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

class CommonSessionHandler
{
    protected ObjectNormalizer $normalizer;
    protected ?string $phpBinary = null;

    protected string $database;

    protected KernelInterface $kernel;

    protected LoggerInterface $gameSessionLogger;

    protected GameList $gameSession;

    protected EntityManagerInterface $entityManager;

    protected EntityManagerInterface $mspServerManagerEntityManager;

    protected readonly ConnectionManager $connectionManager;

    protected readonly ContainerBagInterface $params;

    protected GameSessionLogger $gameSessionLogFileHandler;

    protected WatchdogCommunicator $watchdogCommunicator;

    protected \ZipArchive $saveZip;

    protected GameSave $gameSave;

    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $gameSessionLogger,
        EntityManagerInterface $mspServerManagerEntityManager,
        ConnectionManager $connectionManager,
        ContainerBagInterface $params,
        GameSessionLogger $gameSessionLogFileHandler,
        WatchdogCommunicator $watchdogCommunicator,
    ) {
        $this->kernel = $kernel;
        $this->gameSessionLogger = $gameSessionLogger;
        $this->mspServerManagerEntityManager = $mspServerManagerEntityManager;
        $this->connectionManager = $connectionManager;
        $this->params = $params;
        $this->gameSessionLogFileHandler = $gameSessionLogFileHandler;
        $this->watchdogCommunicator = $watchdogCommunicator;
        $this->normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
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

    private function log(string $level, string $message, array $contextVars = []): void
    {
        $contextVars['gameSession'] = $this->gameSession->getId();
        $this->gameSessionLogger->$level($message, $contextVars);
    }

    protected function info(string $message, array $contextVars = []): void
    {
        $this->log('info', $message, $contextVars);
    }

    protected function debug(string $message, array $contextVars = []): void
    {
        $this->log('debug', $message, $contextVars);
    }

    protected function notice(string $message, array $contextVars = []): void
    {
        $this->log('notice', $message, $contextVars);
    }

    protected function warning(string $message, array $contextVars = []): void
    {
        $this->log('warning', $message, $contextVars);
    }

    protected function error(string $message, array $contextVars = []): void
    {
        $this->log('error', $message, $contextVars);
    }
}
