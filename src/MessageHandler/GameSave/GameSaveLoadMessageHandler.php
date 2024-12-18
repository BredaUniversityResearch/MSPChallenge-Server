<?php

namespace App\MessageHandler\GameSave;

use App\Domain\Common\EntityEnums\GameSaveTypeValue;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Entity\Game;
use App\Entity\ServerManager\GameSave;
use App\Entity\Watchdog;
use App\Logger\GameSessionLogger;
use App\MessageHandler\GameList\CommonSessionHandlerBase;
use App\Message\GameSave\GameSaveLoadMessage;
use App\Repository\GameRepository;
use App\VersionsProvider;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use ZipArchive;
use function App\rcopy;
use function App\rrmdir;

#[AsMessageHandler]
class GameSaveLoadMessageHandler extends CommonSessionHandlerBase
{

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
        parent::__construct(...func_get_args());
    }

    /**
     * @throws \Exception
     */
    public function __invoke(GameSaveLoadMessage $gameSave): void
    {
        $this->setGameSessionAndDatabase($gameSave);
        $this->gameSave = $this->mspServerManagerEntityManager->getRepository(GameSave::class)->find(
            $gameSave->gameSaveId
        ) ?? throw new \Exception('Game save not found, so cannot continue.');
        if ($this->gameSave->getSaveType() != GameSaveTypeValue::FULL) {
            throw new \Exception("Cannot reload a save of type {$this->gameSave->getSaveType()}");
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
     * @throws DecodingExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws \Exception
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
        $this->watchdogCommunicator->changeState($this->gameSession, new GameStateValue('pause'));
        $this->logContainer($this->watchdogCommunicator);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws \Exception
     */
    private function importRasterStore(): void
    {
        $this->resetSessionRasterStore();
        $sessionRasterStore = $this->params->get('app.session_raster_dir').$this->gameSession->getId();
        $sessionRasterStoreTemp = $this->params->get('app.session_raster_dir').'temp';
        $this->info("Unpacking raster files... This could take a bit longer.");
        if (!$this->saveZip->extractTo($sessionRasterStoreTemp)) {
            throw new \Exception('ExtractTo failed.');
        } else {
            $this->debug('ExtractTo succeeded.');
        }
        $this->info("Now moving all raster files to their proper place...");
        rcopy($sessionRasterStoreTemp."/raster", $sessionRasterStore);
        rrmdir($sessionRasterStoreTemp);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Exception
     */
    private function openSaveZip(): void
    {
        $saveZipStore = $this->params->get('app.server_manager_save_dir').
            sprintf($this->params->get('app.server_manager_save_name'), $this->gameSave->getId());
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($saveZipStore)) {
            throw new \Exception("Wasn't able to find ZIP file: {$saveZipStore}");
        }
        $this->saveZip = new ZipArchive();
        if ($this->saveZip->open($saveZipStore) !== true) {
            throw new \Exception("Wasn't able to open the ZIP file: {$saveZipStore}");
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function importSessionRunningConfig(): string
    {
        $sessionConfigFileName = $this->params->get('app.session_config_name');
        $sessionConfigFileNamePrefix = explode("%", $sessionConfigFileName)[0];
        $sessionConfigContents = '';
        for ($i = 0; $i < $this->saveZip->numFiles; $i++) {
            $stat = $this->saveZip->statIndex($i);
            if (str_contains($stat['name'], $sessionConfigFileNamePrefix)) {
                $sessionConfigContents = $this->saveZip->getFromIndex($i);
                break;
            }
        }
        $sessionConfigStore = $this->params->get('app.session_config_dir').
            sprintf($sessionConfigFileName, $this->gameSession->getId());
        file_put_contents($sessionConfigStore, $sessionConfigContents);
        $this->info("Imported the saved session config file to {$sessionConfigStore}");
        return $sessionConfigStore;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Exception
     */
    private function setupSessionDatabase(): void
    {
        if ($this->gameSession->getSessionState() == GameSessionStateValue::HEALTHY) {
            $this->notice('This is a save reload into an existing session.');
            $this->watchdogCommunicator->changeState($this->gameSession, new GameStateValue('end'));
            $this->gameSession->setSessionState(new GameSessionStateValue('request'));
            $this->mspServerManagerEntityManager->flush();
            $this->resetSessionDatabase();
            return;
        }
        $this->notice('This is a save reload into a new session..');
        $this->resetSessionDatabase();
    }

    /**
     * @throws \Exception
     */
    private function importSessionDatabase(): void
    {
        $tempDumpFile = $this->tempStoreDbExportInSaveZip();
        $mysqlBinary = (new ExecutableFinder)->find('mysql');
        $process = new Process([
            $mysqlBinary,
            '--host='.$_ENV['DATABASE_HOST'],
            '--port='.$_ENV['DATABASE_PORT'],
            '--user='.$_ENV['DATABASE_USER'],
            '--password='.$_ENV['DATABASE_PASSWORD'],
            ($_ENV['APP_ENV'] == 'test') ? $this->database.'_test' : $this->database
        ], $this->kernel->getProjectDir(), null, "source {$tempDumpFile}");
        $process->mustRun(fn($type, $buffer) => $this->info($buffer));
        // as usually nothing comes out of the buffer...
        $this->debug('Session database dump import attempt completed.');
        $fileSystem = new Filesystem();
        $fileSystem->remove($tempDumpFile);
    }

    /**
     * @throws \Exception
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
        for ($i = 0; $i < $this->saveZip->numFiles; $i++) {
            $stat = $this->saveZip->statIndex($i);
            if (str_contains($stat['name'], 'db_export_')) {
                $this->saveZip->extractTo($outputDirectory, $stat['name']);
                return $outputDirectory.$stat['name'];
            }
        }
        throw new \Exception('Unable to locate the db_export SQL file in the save Zip');
    }
}
