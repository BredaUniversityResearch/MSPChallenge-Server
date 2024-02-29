<?php

namespace App\MessageHandler\GameSave;

use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameSave;
use App\MessageHandler\GameList\CommonSessionHandler;
use App\Message\GameSave\GameSaveCreationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use ZipArchive;

#[AsMessageHandler]
class GameSaveCreationMessageHandler extends CommonSessionHandler
{
    private ZipArchive $saveZip;
    private GameSave $gameSave;

    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $gameSessionLogger,
        EntityManagerInterface $mspServerManagerEntityManager,
        ConnectionManager $connectionManager,
        ContainerBagInterface $params,
    ) {
        parent::__construct(...func_get_args());
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws \Exception
     */
    public function __invoke(GameSaveCreationMessage $gameSave): void
    {
        $this->setGameSessionAndDatabase($gameSave);
        $this->gameSave = $this->mspServerManagerEntityManager->getRepository(GameSave::class)->find(
            $gameSave->gameSaveId
        ) ?? throw new \Exception('Game save not found, so cannot continue.');

        // no try-catch here as we don't want to use the session log to store any save errors
        $this->createSaveZip();
        $this->addSessionDatabaseExportToZip();
        $this->addSessionRunningConfigToZip();
        $this->addSessionRasterStoreToZip();
        $this->addGameListRecordToZip();
        $this->saveZip->close();
    }

    private function addGameListRecordToZip(): void
    {
        $encoder = new JsonEncoder();
        $serializer = new Serializer([$this->normalizer], [$encoder]);
        $normalizeContext = [
            AbstractNormalizer::CALLBACKS => [
                'gameConfigVersion' => fn($innerObject) => $innerObject->getId(),
                'gameServer' => fn($innerObject) => $innerObject->getId(),
                'gameWatchdogServer' => fn($innerObject) => $innerObject->getId(),
                'sessionState' => fn($innerObject) => ((string) $innerObject),
                'gameState' => fn($innerObject) => ((string) $innerObject),
                'gameVisibility' => fn($innerObject) => ((string) $innerObject),
                'saveType' => fn() => null,
                'saveVisibility' => fn() => null,
                'saveTimestamp' => fn() => null
            ]
        ];
        $gameList = $serializer->serialize($this->gameSave, 'json', $normalizeContext);
        $this->saveZip->addFromString('game_list.json', $gameList);
    }

    private function addSessionRasterStoreToZip(): void
    {
        $rasterStore = $this->params->get('app.session_raster_dir')."{$this->gameSession->getId()}/";
        $finder = new Finder();
        $finder->files()->in($rasterStore);
        foreach ($finder as $rasterFile) {
            $zipFolder = 'raster/';
            if (stripos($rasterFile->getPathname(), "archive") !== false) {
                $zipFolder .= "archive/";
            }
            $this->addFileToSaveZip($rasterFile->getRealPath(), $zipFolder);
        }
    }

    private function addSessionDatabaseExportToZip(): void
    {
        $this->saveZip->addFromString(
            "db_export_{$this->gameSession->getId()}.sql",
            $this->exportSessionDatabase()
        );
    }

    private function addSessionRunningConfigToZip(): void
    {
        $sessionConfigStore = $this->params->get('app.session_config_dir').
            sprintf($this->params->get('app.session_config_name'), $this->gameSession->getId());
        $fileSystem = new FileSystem();
        if ($fileSystem->exists($sessionConfigStore)) {
            $this->addFileToSaveZip($sessionConfigStore);
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws \Exception
     */
    private function createSaveZip(): void
    {
        $saveZipStore = $this->params->get('app.server_manager_save_dir').
            sprintf($this->params->get('app.server_manager_save_name'), $this->gameSave->getId());
        $this->saveZip = new ZipArchive();
        if ($this->saveZip->open($saveZipStore, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Wasn't able to create the ZIP file: {$saveZipStore}");
        }
    }

    private function addFileToSaveZip(string $file, ?string $subFolder = null): void
    {
        $this->saveZip->addFile($file, $subFolder.pathinfo($file, PATHINFO_BASENAME));
    }
}
