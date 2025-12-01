<?php

namespace App\MessageHandler\GameSave;

use App\Domain\Common\EntityEnums\GameSaveTypeValue;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\GameSaveZipFileValidator;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SimulationHelper;
use App\Entity\ServerManager\GameSave;
use App\Logger\GameSessionLogger;
use App\Message\GameSave\GameSaveLoadMessage;
use App\MessageHandler\GameList\CommonSessionHandlerBase;
use App\Entity\SessionAPI\Game;
use App\Repository\SessionAPI\GameRepository;
use App\VersionsProvider;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use function App\rcopy;
use function App\rrmdir;

#[AsMessageHandler]
class GameSaveLoadMessageHandler extends CommonSessionHandlerBase
{
    private GameSaveZipFileValidator $validator;

    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $gameSessionLogger,
        ConnectionManager $connectionManager,
        ContainerBagInterface $params,
        GameSessionLogger $gameSessionLogFileHandler,
        WatchdogCommunicator $watchdogCommunicator,
        VersionsProvider $provider,
        SimulationHelper $simulationHelper
    ) {
        parent::__construct(...func_get_args());
    }

    /**
     * @throws Exception
     */
    public function __invoke(GameSaveLoadMessage $gameSave): void
    {
        $this->setGameSessionAndDatabase($gameSave);
        $this->gameSave = $this->mspServerManagerEntityManager->getRepository(GameSave::class)->find(
            $gameSave->gameSaveId
        ) ?? throw new Exception('Game save not found, so cannot continue.');
        if ($this->gameSave->getSaveType() != GameSaveTypeValue::FULL) {
            throw new Exception("Cannot reload a save of type {$this->gameSave->getSaveType()}");
        }
        try {
            $this->gameSessionLogFileHandler->empty($this->gameSession->getId());
            $this->notice("Save reload into session {$this->gameSession->getName()} initiated. Please wait.");
            $this->openSaveZip();
            $this->validateGameConfig($this->importSessionRunningConfig());
            $this->setupSessionDatabase();
            $this->importSessionDatabase();
            $this->migrateSessionDatabase();
            $this->importRasterStore();
            $this->finaliseSaveLoad();
            $this->notice("Session {$this->gameSession->getName()} loaded and ready for use.");
            $state = 'healthy';
        } catch (\Throwable $e) {
            $this->error(
                "Session {$this->gameSession->getName()} failed to create. {problem}",
                ['problem' => $e->getMessage().' '.$e->getTraceAsString()]
            );
            $state = 'failed';
        }
        $this->gameSession->setSessionState(new GameSessionStateValue($state));
        $this->mspServerManagerEntityManager->flush();
    }

    /**
     * @throws ClientExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function finaliseSaveLoad(): void
    {
        /** @var GameRepository $gameRepo */
        $gameRepo = $this->entityManager->getRepository(Game::class);
        $game = $gameRepo->retrieve();
        $game->setGameConfigfile(sprintf($this->params->get('app.session_config_name'), $this->gameSession->getId()));
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $this->registerSimulations();
        $this->watchdogCommunicator->changeState(
            $this->gameSession->getId(),
            new GameStateValue($this->gameSession->getGameState()),
            $this->gameSession->getGameCurrentMonth()
        );
        $this->logContainer($this->watchdogCommunicator);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     */
    private function importRasterStore(): void
    {
        $this->resetSessionRasterStore();
        $sessionRasterStore = $this->params->get('app.session_raster_dir').$this->gameSession->getId();
        $sessionRasterStoreTemp = $this->params->get('app.session_raster_dir').'temp';
        $this->info("Unpacking raster files...");
        if (!$this->validator->getZipArchive()->extractTo($sessionRasterStoreTemp)) {
            throw new Exception('ExtractTo failed.');
        } else {
            $this->debug('ExtractTo succeeded.');
        }
        $this->info("Now moving all raster files to their proper place... This could take a bit longer.");
        rcopy($sessionRasterStoreTemp."/raster", $sessionRasterStore);
        rrmdir($sessionRasterStoreTemp);
        $this->info("Raster files moved.");
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    private function openSaveZip(): void
    {
        $saveZipStore = $this->params->get('app.server_manager_save_dir').
            sprintf($this->params->get('app.server_manager_save_name'), $this->gameSave->getId());
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($saveZipStore)) {
            throw new Exception("Wasn't able to find ZIP file: {$saveZipStore}");
        }
        $this->validator = new GameSaveZipFileValidator(
            $saveZipStore,
            $this->kernel,
            $this->connectionManager
        );
        if (!$this->validator->isValid()) {
            throw new Exception("ZIP file {$saveZipStore} is invalid: {$this->validator->getErrorsAsString()}.");
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function importSessionRunningConfig(): string
    {
        $sessionConfigContents = $this->validator->getSessionConfigContents();
        $sessionConfigFileName = $this->params->get('app.session_config_name');
        $sessionConfigStore = $this->params->get('app.session_config_dir').
            sprintf($sessionConfigFileName, $this->gameSession->getId());
        file_put_contents($sessionConfigStore, $sessionConfigContents);
        $this->info("Imported the saved session config file to {$sessionConfigStore}");
        return $sessionConfigStore;
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    private function setupSessionDatabase(): void
    {
        if ($this->gameSession->getSessionState() == GameSessionStateValue::HEALTHY) {
            $this->notice('This is a save reload into an existing session.');
            $this->watchdogCommunicator->changeState(
                $this->gameSession->getId(),
                new GameStateValue('end'),
                $this->gameSession->getGameCurrentMonth()
            );
            $this->gameSession->setSessionState(new GameSessionStateValue('request'));
            $this->mspServerManagerEntityManager->flush();
            $this->resetSessionDatabase();
            return;
        }
        $this->notice('This is a save reload into a new session..');
        $this->resetSessionDatabase();
    }

    /**
     * @throws Exception
     */
    private function importSessionDatabase(): void
    {
        $this->debug('Session database dump import attempt starting... This might take a while.');
        $tempDumpFile = $this->tempStoreDbExportInSaveZip();
        $mysqlBinary = (new ExecutableFinder)->find('mysql');
        $process = new Process([
            $mysqlBinary,
            '--host='.$_ENV['DATABASE_HOST'],
            '--port='.$_ENV['DATABASE_PORT'],
            '--user='.$_ENV['DATABASE_USER'],
            '--password='.$_ENV['DATABASE_PASSWORD'],
            '--skip-ssl',
            ($_ENV['APP_ENV'] == 'test') ? $this->database.'_test' : $this->database
        ], $this->kernel->getProjectDir(), null, "source {$tempDumpFile}", 300);
        $process->mustRun(fn($type, $buffer) => $this->info($buffer));
        // as usually nothing comes out of the buffer...
        $this->debug('Session database dump import attempt completed.');
        $fileSystem = new Filesystem();
        $fileSystem->remove($tempDumpFile);
    }

    /**
     * @throws Exception
     */
    private function tempStoreDbExportInSaveZip(): string
    {
        $fileSystem = new Filesystem();
        $outputDirectory = "{$this->kernel->getProjectDir()}/export/DatabaseDumps/";
        if (!$fileSystem->exists($outputDirectory)) {
            $fileSystem->mkdir($outputDirectory);
            // umask issues in prod can prevent mkdir to create with default 0777
            $fileSystem->chmod($outputDirectory, 0777);
        }
        $this->validator->getZipArchive()->extractTo($outputDirectory, $this->validator->getDbDumpFilename());
        return $outputDirectory.$this->validator->getDbDumpFilename();
    }
}