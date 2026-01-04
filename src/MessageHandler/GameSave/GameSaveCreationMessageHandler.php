<?php

namespace App\MessageHandler\GameSave;

use App\Domain\Common\EntityEnums\GameSaveTypeValue;
use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Common\GameListAndSaveSerializer;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SimulationHelper;
use App\Entity\ServerManager\GameSave;
use App\Logger\GameSessionLogger;
use App\Message\GameSave\GameSaveCreationMessage;
use App\MessageHandler\GameList\CommonSessionHandler;
use App\Entity\SessionAPI\Layer;
use App\Repository\SessionAPI\LayerRepository;
use App\VersionsProvider;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileWriter;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use ZipArchive;

#[AsMessageHandler]
class GameSaveCreationMessageHandler extends CommonSessionHandler
{

    private ?string $shapeFileTempStore = null;

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
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     */
    public function __invoke(GameSaveCreationMessage $gameSave): void
    {
        $this->setGameSessionAndDatabase($gameSave);
        $this->gameSave = $this->mspServerManagerEntityManager->getRepository(GameSave::class)->find(
            $gameSave->gameSaveId
        ) ?? throw new Exception('Game save not found, so cannot continue.');

        $this->createSaveZip();
        if ($this->gameSave->getSaveType() == GameSaveTypeValue::LAYERS) {
            $this->addLayerShapeFilesExportsToZip();
            $this->addLayerRasterExportsToZip();
        } else {
            $this->addSessionDatabaseExportToZip();
            $this->addSessionRunningConfigToZip();
            $this->addSessionRasterStoreToZip();
            $this->addGameListRecordToZip();
        }
        $this->closeSaveZip();
        $this->mspServerManagerEntityManager->flush();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    private function closeSaveZip(): void
    {
        $this->saveZip->close();
        $this->deleteShapeFilesTempStore();
    }

    private function createShapeFilesTempStore(): void
    {
        $this->shapeFileTempStore = $this->params->get('app.server_manager_save_dir').'temp_'.rand(0, 1000).'/';
        $fileSystem = new Filesystem();
        $fileSystem->mkdir($this->shapeFileTempStore);
        // umask issues in prod can prevent mkdir to create with default 0777
        $fileSystem->chmod($this->shapeFileTempStore, 0777);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    private function deleteShapeFilesTempStore(): void
    {
        if (is_null($this->shapeFileTempStore)) {
            return; // full saves don't create a temp store
        }
        $saveZipStore = $this->params->get('app.server_manager_save_dir').
            sprintf($this->params->get('app.server_manager_save_name'), $this->gameSave->getId());
        $fileSystem = new Filesystem();
        while (!$fileSystem->exists($saveZipStore)) {
            sleep(1); // bit ugly, I know, but Zip file creation takes a bit of time...
            $counter = ($counter ?? 0) + 1;
            if ($counter > 9) {
                throw new Exception("Waited {$counter} seconds, but Zip file {$saveZipStore} still not there...");
            }
        }
        $finder = new Finder();
        $finder->files()->in($this->shapeFileTempStore);
        foreach ($finder as $file) {
            $fileSystem->remove($file->getRealPath());
        }
        $fileSystem->remove($this->shapeFileTempStore);
    }

    /**
     * @throws Exception
     */
    private function createLayerShapeFilesAndStore(string $layerName, array $geometryContent): void
    {
        if ($geometryContent['layerGeoType'] == LayerGeoType::POLYGON) {
            $shapeType = Shapefile::SHAPE_TYPE_POLYGON;
            $shapeTypeClass = "Shapefile\Geometry\MultiPolygon";
        } elseif ($geometryContent['layerGeoType'] == LayerGeoType::LINE) {
            $shapeType = Shapefile::SHAPE_TYPE_POLYLINE;
            $shapeTypeClass = "Shapefile\Geometry\MultiLinestring";
        } elseif ($geometryContent['layerGeoType'] == LayerGeoType::POINT) {
            $shapeType = Shapefile::SHAPE_TYPE_POINT;
            $shapeTypeClass = "Shapefile\Geometry\Point";
        } else {
            $this->gameSave->addToSaveNotes("Unable to identify type of geometry for {$layerName}, so skipping.\n\n");
            return;
        }
        $newShapefile = new ShapefileWriter("{$this->shapeFileTempStore}{$layerName}.shp", [
            Shapefile::OPTION_EXISTING_FILES_MODE => Shapefile::MODE_OVERWRITE,
            Shapefile::OPTION_DBF_FORCE_ALL_CAPS => false]);
        $newShapefile->setShapeType($shapeType);
        // all other fields under 'data' will be defined further down
        $newShapefile->addField('mspid', Shapefile::DBF_TYPE_CHAR, 80, 0);
        $newShapefile->addField('type', Shapefile::DBF_TYPE_CHAR, 80, 0);
        $additionalFieldsAdded = [];
        $alreadySavedErrorType = [];
        foreach ($geometryContent['geometry'] as $count => $geometryEntry) {
            try {
                $dataArray = [];
                $dataArray['mspid'] = $geometryEntry['mspid'] ?? '';
                $dataArray['type'] = $geometryEntry['type'] ?? '';
                $additionalData = $geometryEntry['data'] ?? [];
                foreach ($additionalData as $fieldName => $fieldValue) {
                    // 1. skipping duplicate TYPE definition here - it's completely unnecessary and creates problems
                    // 2. skipping anything with name > 10 char (notably Shipping_Intensity) - ShapeFile no likey
                    if ('type' != $fieldName && 'TYPE' != $fieldName && strlen($fieldName) <= 10) {
                        if (!in_array($fieldName, $additionalFieldsAdded) && 0 == $count) {
                            $newShapefile->addField($fieldName, Shapefile::DBF_TYPE_CHAR, 254, 0);
                            $additionalFieldsAdded[] = $fieldName;
                        }
                        if (in_array($fieldName, $additionalFieldsAdded)) {
                            $dataArray[$fieldName] = $fieldValue;
                        }
                    }
                }
                foreach ($additionalFieldsAdded as $fieldNameToCheck) {
                    if (!isset($dataArray[$fieldNameToCheck])) {
                        $dataArray[$fieldNameToCheck] = '';
                    }
                }
                if ("Shapefile\Geometry\MultiPolygon" == $shapeTypeClass) {
                    $geometry = new $shapeTypeClass([], Shapefile::ACTION_FORCE);
                } else {
                    $geometry = new $shapeTypeClass();
                }
                $geometry->initFromGeoJSON(json_encode($geometryEntry['the_geom']));
                $geometry->setDataArray($dataArray);
                $newShapefile->writeRecord($geometry);
            } catch (ShapefileException $e) {
                if (!in_array($e->getErrorType(), $alreadySavedErrorType)) {
                    $this->gameSave->addToSaveNotes(
                        "Problem adding geometry from {$layerName}. {$e->getErrorType()}: {$e->getMessage()}."
                    );
                    if (!empty($e->getDetails())) {
                        $this->gameSave->addToSaveNotes("Details: {$e->getDetails()}. ");
                    }
                    $this->gameSave->addToSaveNotes(
                        'Further errors of this type for this entire layer will not be logged.\n\n'
                    );
                    $alreadySavedErrorType[] = $e->getErrorType();
                }
                continue;
            }
        }
        $newShapefile = null; // might seem trivial, but without this the library can fail
    }

    /**
     * @throws Exception
     */
    private function addLayerShapeFilesExportsToZip(): void
    {
        $this->createShapeFilesTempStore();
        /** @var LayerRepository $repo */
        $repo = $this->entityManager->getRepository(Layer::class);
        $layerGeometry = $repo->getAllGeometryDecodedGeoJSON();
        foreach ($layerGeometry as $layerName => $geometryContent) {
            $this->createLayerShapeFilesAndStore($layerName, $geometryContent);
        }
        $finder = new Finder();
        $finder->files()->in($this->shapeFileTempStore);
        foreach ($finder as $file) {
            $this->addFileToSaveZip($file->getRealPath());
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    private function addLayerRasterExportsToZip(): void
    {
        $finder = new Finder();
        $finder->files()->in("{$this->params->get('app.session_raster_dir')}{$this->gameSession->getId()}/");
        foreach ($finder as $rasterFile) {
            $this->addFileToSaveZip($rasterFile->getRealPath());
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function addGameListRecordToZip(): void
    {
        $serializer = new GameListAndSaveSerializer($this->connectionManager->getServerManagerEntityManager());
        $gameList = $serializer->createJsonFromGameSave($this->gameSave);
        $this->saveZip->addFromString('game_list.json', $gameList);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
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

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
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
     * @throws Exception
     */
    private function createSaveZip(): void
    {
        $saveZipStore = $this->params->get('app.server_manager_save_dir').
            sprintf($this->params->get('app.server_manager_save_name'), $this->gameSave->getId());
        $fileSystem = new Filesystem();
        if ($fileSystem->exists($saveZipStore)) {
            $fileSystem->remove($saveZipStore);
        }
        $this->saveZip = new ZipArchive();
        if ($this->saveZip->open($saveZipStore, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Wasn't able to create the ZIP file: {$saveZipStore}");
        }
    }

    /**
     * @throws Exception
     */
    private function addFileToSaveZip(string $file, ?string $subFolder = null): void
    {
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($file)) {
            throw new Exception("Could not add {$file} to zip as it does not exist");
        }
        if (!$this->saveZip->addFile($file, $subFolder . pathinfo($file, PATHINFO_BASENAME))) {
            throw new Exception("Could not add {$file} to zip");
        }
    }
}
