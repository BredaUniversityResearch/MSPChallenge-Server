<?php

namespace App\Domain\API\v1;

use App\Domain\Services\ConnectionManager;
use App\Repository\SessionAPI\LayerRepository;
use Exception;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class Layer extends Base
{
    private GeoServer $geoServer;

    public function __construct()
    {
        $this->geoServer = new GeoServer();
    }

    public function getGeoServer(): GeoServer
    {
        return $this->geoServer;
    }

    /**
     * @apiGroup Layer
     * @apiDescription Set a layer as inactive, without actually deleting it completely from the session database
     * @api {POST} /layer/Delete/
     * @apiParam {int} layer_id Target layer id
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Delete(int $layer_id): void
    {
        $this->getDatabase()->query("UPDATE layer SET layer_active=? WHERE layer_id=?", array(0, $layer_id));
    }

    /**
     * @apiGroup Layer
     * @apiDescription Export a layer to .json
     * @throws Exception
     * @api {POST} /layer/Export/ Export
     * @apiParam {int} layer_id id of the layer to export
     * @apiSuccess {string} json formatted layer export with all geometry and their attributes
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Export(int $layer_id): array
    {
        $layer = $this->getDatabase()->query("SELECT * FROM layer WHERE layer_id=?", array($layer_id));
        if (empty($layer)) {
            throw new Exception("Layer not found.");
        }
        $layer = $layer[0];

        $geometry = $this->getDatabase()->query(
            "
            SELECT geometry_id, geometry_FID, geometry_geometry, geometry_layer_id, geometry_type, geometry_data,
                geometry_mspid
            FROM layer 
            LEFT JOIN geometry ON geometry_layer_id=layer.layer_id 
            LEFT JOIN plan_layer ON plan_layer_layer_id=layer.layer_id
            LEFT JOIN plan ON plan_layer_plan_id=plan.plan_id
            WHERE geometry.geometry_active=? AND (
                layer_id=? OR layer_original_id=?
            ) AND (geometry_subtractive=? OR geometry_subtractive IS NULL)
              AND (plan_state=? OR plan_state=? OR plan_state IS NULL)
            ",
            array(1, $layer_id, $layer_id, 0, "APPROVED", "IMPLEMENTED")
        ); // getting all active geometry, except those within plans that are not APPROVED or not IMPLEMENTED

        // getting all active subtractive geometry for this layer, which only occurs in the original layer
        //   dataset because the client doesn't support adding/editing/deleting subtractive geometry ('holes')
        $subtractiveArr = $this->getDatabase()->query(
            "
            SELECT geometry_id, geometry_FID, geometry_geometry, geometry_layer_id, geometry_type, geometry_data,
                geometry_subtractive 
            FROM geometry 
            WHERE geometry_layer_id=? AND geometry_subtractive<>? AND geometry_active=?
            ",
            array($layer_id, 0, 1)
        );

        $all = array();
            
        // this part actually subtracts the latter geometry from the former geometry
        foreach ($geometry as $shape) {
            $g = array();

            $geom = $shape['geometry_geometry'];

            $g["FID"] = $shape["geometry_FID"];

            switch ($layer['layer_geotype']) {
                case "polygon":
                    $g["the_geom"] = "MULTIPOLYGON (" . str_replace("[", "(", $geom);
                    $g["the_geom"] = str_replace("]", ")", $g["the_geom"]) . ")";


                    $g["the_geom"] = str_replace(",", " ", $g["the_geom"]);
                    $g["the_geom"] = str_replace(") (", ", ", $g["the_geom"]);

                    $g["the_geom"] = substr($g["the_geom"], 0, -2);

                    $hassubs = false;
                    $geostring = "";

                    foreach ($subtractiveArr as $sub) {
                        if (isset($sub['geometry_subtractive']) &&
                            $sub['geometry_subtractive'] == $shape['geometry_id']
                        ) {
                            if (!$hassubs) {
                                $hassubs = true;
                            }

                            $geostring .= ",(";

                            $geom = json_decode($sub['geometry_geometry'], true, 512, JSON_BIGINT_AS_STRING);
                            foreach ($geom as $geo) {
                                $geostring .= $geo[0] . " " . $geo[1] . ", ";
                            }
                            $geostring = substr($geostring, 0, -2);
                            $geostring .= ")";
                        }
                    }

                    $g["the_geom"] .= $geostring . "))";
                    break;
                case "line":
                    $g["the_geom"] = "MULTILINESTRING " . str_replace("[", "(", $geom);
                    $g["the_geom"] = str_replace("]", ")", $g["the_geom"]);


                    $g["the_geom"] = str_replace(",", " ", $g["the_geom"]);
                    $g["the_geom"] = str_replace(") (", ", ", $g["the_geom"]);
                    break;
                case "point":
                    $geom = json_decode($geom, true)[0];
                    $g["the_geom"] = "POINT (" . $geom[0] ." " . $geom[1] . ")";
                    break;
            }

            $g["type"] = $shape["geometry_type"];
            $g["mspid"] = $shape["geometry_mspid"];
            $g["id"] = $shape["geometry_id"];

            $data = json_decode($shape['geometry_data'], true);
            if (!empty($data) && is_array($data)) {
                foreach ($data as $key => $metadata) {
                    $g['data'][$key] = $metadata;
                }
            }

            array_push($all, $g);
        }

        return $all;
    }

    /**
     * @apiGroup Layer
     * @apiDescription Get all geometry in a single layer
     * @param int $layer_id
     * @return array
     * @throws Exception
     * @api {POST} /layer/get/ Get
     * @apiParam {int} layer_id id of the layer to return
     * @apiSuccess {string} JSON JSON object
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Get(int $layer_id): array
    {
        $vectorCheck = $this->getDatabase()->query(
            "SELECT layer_geotype FROM layer WHERE layer_id = ?",
            array($layer_id)
        );
        if (empty($vectorCheck[0]["layer_geotype"]) || $vectorCheck[0]["layer_geotype"] == "raster") {
            throw new Exception("Not a vector layer.");
        }
            
        $data = $this->getDatabase()->query("SELECT 
					geometry_id as id, 
					geometry_geometry as geometry, 
					geometry_country_id as country,
					geometry_FID as FID,
					geometry_data as data,
					geometry_layer_id as layer,
					geometry_subtractive as subtractive,
					geometry_type as type,
					geometry_persistent as persistent,
					geometry_mspid as mspid,
					geometry_active as active
				FROM layer 
				LEFT JOIN geometry ON layer.layer_id=geometry.geometry_layer_id
				WHERE layer.layer_id = ? ORDER BY geometry_FID, geometry_subtractive", array($layer_id));
            
        if (empty($data) || empty($data[0]["geometry"])) {
            return [];
        }

        return self::MergeGeometry($data);
    }

    /**
     * @apiGroup Layer
     * @throws Exception
     * @api {POST} /layer/GetRaster GetRaster
     * @apiParam layer_name Name of the layer corresponding to the image data.
     * @apiDescription Retrieves image data for raster.
     * @apiSuccess Returns array of displayed_bounds and image_data strings to payload, whereby image_data is base64
     *   encoded file
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetRaster(string $layer_name, int $month = -1): array
    {
        $repo = ConnectionManager::getInstance()->getGameSessionEntityManager($this->getGameSessionId())
            ->getRepository(\App\Entity\SessionAPI\Layer::class);
        if (null === $layer = $repo->findOneBy(['layerName' => $layer_name])) {
            throw new Exception("Could not find layer with name " . $layer_name . " to request the raster image for");
        }
        $rasterData = $layer->getLayerRaster();
        if (null === $url = $rasterData->getUrl()) {
            throw new Exception("Could not find raster file for layer with name " . $layer_name);
        }
        $path_parts = pathinfo($url);
        $fileExt = $path_parts['extension'];
        $filename = $path_parts['filename'];
        $archivedRasterDataUrlFormat = "archive/%s_%d.%s";

        // use the archive first if it exists
        $filePath = Store::GetRasterStoreFolder($this->getGameSessionId()).
            sprintf($archivedRasterDataUrlFormat, $filename, $month, $fileExt);
        // if not, traverse backwards in time until one is found
        while (!file_exists($filePath) && $month >= 0) {
            $month--;
            $filePath = Store::GetRasterStoreFolder($this->getGameSessionId()).
                sprintf($archivedRasterDataUrlFormat, $filename, $month, $fileExt);
        }

        // if we still haven't found it, try the original path
        if ($filePath == null || !file_exists($filePath)) {
            throw new Exception(
                "Could not find raster file for layer with name " . $layer_name . " at path " . $filePath
            );
        }
        $this->log("Retrieved raster image for layer with name " . $layer_name . " from path " . $filePath);
        ;
        $imageData = file_get_contents($filePath);
        $result['displayed_bounds'] = $rasterData->getBoundingbox();
        $result['image_data'] = base64_encode($imageData);
        $result['logs'] = $this->getLogMessages();
        return $result;
    }

    /**
     * @apiGroup Layer
     * @throws Exception
     * @api {POST} /layer/List
     * @apiDescription List Provides a list of raster layers and vector layers that have active geometry.
     * @apiParam {array} layer_tags Optional array of tags to filter the layers by.
     * @apiSuccess Returns an array of layers, with layer_id, layer_name and layer_geotype objects defined per layer.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function List(?array $layer_tags = null): array
    {
        $db = ConnectionManager::getInstance()->getCachedGameSessionDbConnection($this->getGameSessionId());
        $qb = $db->createQueryBuilder()
            ->select('l.layer_id', 'l.layer_name', 'l.layer_geotype')
            ->from('layer', 'l')
            ->leftJoin('l', 'geometry', 'g', 'l.layer_id = g.geometry_layer_id AND g.geometry_active = 1')
            ->where('l.layer_name <> ""')
            ->groupBy('l.layer_name');
        if (!empty($layer_tags)) {
            $qb->andWhere('JSON_CONTAINS(l.layer_tags, :layer_tags)')
                ->setParameter('layer_tags', json_encode($layer_tags));
        }
        return $db->executeQuery($qb->getSQL(), $qb->getParameters())->fetchAllAssociative();
    }

    /**
     * @apiGroup Layer
     * @apiDescription Get all the meta data of a single layer
     * @throws Exception
     * @api {POST} /layer/meta/ Meta
     * @apiParam {int} layer_id layer id to return
     * @apiSuccess {string} JSON JSON Object
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Meta(int $layer_id): array
    {
        return $this->GetMetaForLayerById($layer_id);
    }

    /**
     * @apiGroup Layer
     * @apiDescription Gets a single layer meta data by name.
     * @throws Exception
     * @api {POST} /layer/MetaByName MetaByName
     * @apiParam {string} name name of the layer that we want the meta for
     * @apiSuccess {string} JSON JSON Object.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function MetaByName(string $name): array
    {
        $result = [];
        $layerID = $this->getDatabase()->query("SELECT layer_id FROM layer where layer_name=?", array($name));
        if (count($layerID) > 0) {
            $result = $this->GetMetaForLayerById($layerID[0]["layer_id"]);
        } else {
            self::Debug("Could not find layer with name ".$name);
        }
        return $result;
    }

    /**
     * @apiGroup Layer
     * @apiDescription Update the meta data of a layer
     * @throws Exception
     * @api {POST} /layer/UpdateMeta UpdateMeta
     * @apiParam {string} short Update the display name of a layer
     * @apiParam {string} category Update the category of a layer
     * @apiParam {string} subcategory Update the subcategory of a layer
     * @apiParam {string} type Update the type field of a layer
     * @apiParam {int} depth Update the depth of a layer
     * @apiParam {int} id id of the layer to update
     * @apiSuccess {int} id id of the new layer
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateMeta(
        string $short,
        string $category,
        string $subcategory,
        string $type,
        int $depth,
        int $id
    ): void {
        $this->getDatabase()->query(
            "
            UPDATE layer SET layer_short=?, layer_category=?, layer_subcategory=?, layer_type=?, layer_depth=?
            WHERE layer_id=?
            ",
            array($short, $category, $subcategory, $type, $depth, $id)
        );
    }

    /**
     * @throws Exception
     */
    // @todo: remove raster_bounds support ? REL/REL/RiskModel.cs in simulations uses it, but REL isn't used anymore?
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateRaster(
        \App\Entity\SessionAPI\Layer $layer,
        string $imageData,
        array $raster_bounds = null,
        ?int $month = null
    ): void {
        $rasterData = $layer->getLayerRaster();
        if ($rasterData?->getUrl() === null) {
            throw new Exception("Could not update raster for layer ".$layer->getLayerName().". ".
                " raster file name was not specified in raster metadata");
        }

        $game = new Game();
        $this->asyncDataTransferTo($game);
        $curMonth = $game->GetCurrentMonthAsId();
        $month ??= $curMonth;

        // we are processing the next month, so:
        // - replace the current one in raster/ folder,
        // - allow raster bounds updates
        if ($month == $curMonth) {
            Store::EnsureFolderExists(Store::GetRasterStoreFolder($this->getGameSessionId()));
            $f = Store::GetRasterStoreFolder($this->getGameSessionId()).$rasterData->getUrl();

            try {
                $fileSystem = new Filesystem();
                $fileSystem->dumpFile(
                    $f,
                    $imageData
                );
                $this->log('Stored image_data to '.$f);
            } catch (IOException $e) {
                $this->log('Failed to save given image_data to '.$f.':'.$e->getMessage(), self::LOG_LEVEL_ERROR);
            }

            // raster bounds update
            $rasterDataUpdated = false;
            if (!empty($raster_bounds)) {
                $this->log('Set raster for layer '.$layer->getLayerName());
                $layer->setLayerRaster($rasterData->setBoundingbox($raster_bounds));
                $rasterDataUpdated = true;
            }

            // update the layer record
            $layer
                ->setLayerLastupdate(microtime(true))
                ->setLayerMelupdate(1);
            $this->log(sprintf(
                'Updated layer %s with id %d',
                ($rasterDataUpdated ? ' incl. raster data' : ''),
                $layer->getLayerId()
            ));
        }

        // (Pre-)archive the raster file
        Store::EnsureFolderExists(Store::GetRasterArchiveFolder($this->getGameSessionId()));
        $layerPathInfo = pathinfo($rasterData->getUrl());
        $layerFileName = $layerPathInfo["filename"];
        $layerFileExt = $layerPathInfo["extension"];
        $newFileName = $layerFileName."_".$month.".".$layerFileExt;
        $f = Store::GetRasterArchiveFolder($this->getGameSessionId()).$newFileName;

        try {
            $fileSystem = new Filesystem();
            $fileSystem->dumpFile(
                $f,
                $imageData
            );
            $this->log('Archived image_data to '.$f);
        } catch (IOException $e) {
            $this->log('Failed to archive image_data to '.$f.':'.$e->getMessage(), self::LOG_LEVEL_ERROR);
        }
    }

    /**
     * @throws Exception|ExceptionInterface
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetMetaForLayerById(int $layerId): array
    {
        /** @var LayerRepository $repo */
        $repo = ConnectionManager::getInstance()->getGameSessionEntityManager($this->getGameSessionId())
            ->getRepository(\App\Entity\SessionAPI\Layer::class);
        return $repo->normalise($repo->find($layerId));
    }
}
