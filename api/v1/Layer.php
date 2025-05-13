<?php

namespace App\Domain\API\v1;

use App\Domain\Services\ConnectionManager;
use Exception;

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
     * @return array|null
     * @throws Exception
     * @api {POST} /layer/get/ Get
     * @apiParam {int} layer_id id of the layer to return
     * @apiSuccess {string} JSON JSON object
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Get(int $layer_id): ?array
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
            return null;
        }

        if (false === $result = self::MergeGeometry($data)) {
            return null;
        }

        return $result;
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
        $layerData = $this->getDatabase()->query(
            "SELECT layer_id, layer_raster FROM layer WHERE layer_name = ?",
            array($layer_name)
        );
        if (count($layerData) != 1) {
            throw new Exception("Could not find layer with name " . $layer_name . " to request the raster image for");
        }

        $filePath = null;
        if (null !== $rasterData = json_decode($layerData[0]['layer_raster'], true)) {
            $filePath = Store::GetRasterStoreFolder($this->getGameSessionId()).$rasterData['url'];
        }

        $path_parts = pathinfo($rasterData['url']);
        $fileExt = $path_parts['extension'];
        $filename = $path_parts['filename'];
        $archivedRasterDataUrlFormat = "archive/%s_%d.%s";
        if ($month >= 0) {
            $filePath = Store::GetRasterStoreFolder($this->getGameSessionId()).
                sprintf($archivedRasterDataUrlFormat, $filename, $month, $fileExt);
            while (!file_exists($filePath) && $month > 0) {
                $month--;
                $filePath = Store::GetRasterStoreFolder($this->getGameSessionId()).
                    sprintf($archivedRasterDataUrlFormat, $filename, $month, $fileExt);
            }
        }
            
        if ($filePath === null || !file_exists($filePath)) {
            // final try.... if $month = 0 and you couldn't find the file so far, just return the very original
            //  (which is either the original path when nothing is archived or the archived one of the setup phase)
            if ($month == 0) {
                $filePath = Store::GetRasterStoreFolder($this->getGameSessionId()).
                    sprintf($archivedRasterDataUrlFormat, $filename, -1, $fileExt);
                if (!file_exists($filePath)) {
                    $filePath = Store::GetRasterStoreFolder($this->getGameSessionId()).$rasterData['url'];
                }
                if (!file_exists($filePath)) {
                    throw new Exception(
                        "Could not find raster file for layer with name " . $layer_name . " at path " . $filePath
                    );
                }
            } else {
                throw new Exception(
                    "Could not find raster file for layer with name " . $layer_name . " at path " . $filePath
                );
            }
        }
        $imageData = file_get_contents($filePath);

        $result['displayed_bounds'] = $rasterData['boundingbox'];
        $result['image_data'] = base64_encode($imageData);
        return $result;
    }

    /**
     * @apiGroup Layer
     * @apiDescription Import metadata for a set of layers as defined under 'meta' in the session's config file
     * @throws Exception
     * @api {POST} /layer/ImportMeta
     * @apiParam {string} configFilename
     * @apiParam {string} geoserver_url
     * @apiParam {string} geoserver_username
     * @apiParam {string} geoserver_password
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ImportMeta(
        string $configFilename,
        string $geoserver_url,
        string $geoserver_username,
        string $geoserver_password
    ): void {
        $game = new Game();
        $data = $game->GetGameConfigValues($configFilename);
        $metaData = $data['meta'];
        $region = $data['region'];

        $geoServer = new GeoServer();
        $geoServer
            ->setBaseurl($geoserver_url)
            ->setUsername($geoserver_username)
            ->setPassword($geoserver_password);
        $allLayers = $geoServer->GetAllRemoteLayers($region);

        foreach ($metaData as $layerMetaData) {
            $dbLayerId = $this->VerifyLayerExists($layerMetaData["layer_name"], $allLayers);
            if ($dbLayerId != -1) {
                $this->ImportMetaForLayer($layerMetaData, $dbLayerId);
                $this->VerifyLayerTypesForLayer($layerMetaData, $dbLayerId);
            } else {
                Log::LogWarning("Could not find layer with name ".$layerMetaData["layer_name"]." in the database");
            }
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function VerifyLayerTypesForLayer(array $layerData, int $layerId): void
    {
        $allTypes = $this->getDatabase()->query(
            "SELECT geometry_type FROM geometry WHERE geometry_layer_id=? GROUP BY geometry_type",
            array($layerId)
        );
        $jsonType = $layerData['layer_type'];
        $errorTypes = array();

        foreach ($allTypes as $t) {
            $typelist = explode(",", $t['geometry_type']);
            foreach ($typelist as $singletype) {
                //set a default type if one wasn't found
                if (!isset($jsonType[$singletype]) && !in_array($singletype, $errorTypes)) {
                    self::Error($layerData['layer_name'] . " Type " . $singletype .
                        " was set in the geometry but was not found in the config file");
                    array_push($errorTypes, $singletype);

                    //update the json array with the new type if it's not set, just to avoid errors on the client
                    $jsonType[$singletype] = json_decode(
                        "{\"displayName\" : \"default\",\"displayPolygon\":true,\"polygonColor\":\"#6CFF1C80\",
                        \"polygonPatternName\":5,\"displayLines\":true,\"lineColor\":\"#7AC943FF\",
                        \"displayPoints\":false,\"pointColor\":\"#7AC943FF\",\"pointSize\":1.0}",
                        true
                    );
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function VerifyLayerExists(string $layerName, array $struct): int
    {
        //check if the layer exists
        $d = $this->getDatabase()->query("SELECT layer_id FROM layer WHERE layer_name=?", array($layerName));

        if (empty($d)) {
            Log::LogWarning($layerName ." was not found. Has this layer been renamed or removed?");
            if (in_array($layerName, $struct)) {
                self::Error($layerName . " exists on geoserver but not in your database. Try recreating the database.");
            } else {
                self::Error($layerName . " has not been found on geoserver. Are you sure this file exists?");
            }
            return -1;
        } else {
            return $d[0]["layer_id"];
        }
    }

    /**
     * @param string $key
     * @param mixed|null $val
     * @return mixed|null
     */
    private function metaValueValidation(string $key, $val)
    {
        // all key-based validation first
        if ($key == 'layer_type') {
            return json_encode($val, JSON_FORCE_OBJECT);
        }
        $convertZeroToNull = [
            'layer_entity_value_max' // float - used to convert 0.0 to null
        ];
        if (in_array($key, $convertZeroToNull)) {
            if (empty($val)) {
                return null; // meaning: null returned if value was "", null, 0, 0.0 or false
            }
        }

        // all value-based validation second
        if (is_array($val) || is_object($val)) {
            if (false === $result = json_encode($val)) {
                return '';
            }
            return $result;
        }
        if ($val == null) {
            return '';
        }

        return $val;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function ImportMetaForLayer(array $layerData, int $dbLayerId)
    {
        //these meta vars are to be ignored in the importer
        $ignoreList = array(
            "layer_id",
            "layer_name",
            "layer_original_id",
            "layer_raster",
            "layer_width",
            "layer_height",
            "layer_raster_material",
            "layer_raster_pattern",
            "layer_raster_minimum_value_cutoff",
            "layer_raster_color_interpolation",
            "layer_raster_filter_mode",
            "approval",
            "layer_download_from_geoserver",
            "layer_property_as_type"
        );

        $inserts = "";
        $insertarr = array();
        foreach ($layerData as $key => $val) {
            if (in_array($key, $ignoreList)) {
                continue;
            } else {
                $inserts .= $key . "=?, ";
                array_push($insertarr, $this->metaValueValidation($key, $val));
            }
        }

        $inserts = substr($inserts, 0, -2);

        array_push($insertarr, $dbLayerId);
        $this->getDatabase()->query("UPDATE layer SET " . $inserts . " WHERE layer_id=?", $insertarr);

        //Import raster specific information.
        if ($layerData["layer_geotype"] == "raster") {
            $sqlRasterInfo = $this->getDatabase()->query(
                "SELECT layer_raster FROM layer WHERE layer_id=?",
                array($dbLayerId)
            );
            $existingRasterInfo = json_decode($sqlRasterInfo[0]["layer_raster"], true);

            if (isset($layerData["layer_raster_material"])) {
                $existingRasterInfo["layer_raster_material"] = $layerData["layer_raster_material"];
            }
            if (isset($layerData["layer_raster_pattern"])) {
                $existingRasterInfo["layer_raster_pattern"] = $layerData["layer_raster_pattern"];
            }
            if (isset($layerData["layer_raster_minimum_value_cutoff"])) {
                $existingRasterInfo["layer_raster_minimum_value_cutoff"] =
                    $layerData["layer_raster_minimum_value_cutoff"];
            }
            if (isset($layerData["layer_raster_color_interpolation"])) {
                $existingRasterInfo["layer_raster_color_interpolation"] =
                    $layerData["layer_raster_color_interpolation"];
            }
            if (isset($layerData["layer_raster_filter_mode"])) {
                $existingRasterInfo["layer_raster_filter_mode"] = $layerData["layer_raster_filter_mode"];
            }

            $this->getDatabase()->query(
                "
                UPDATE layer SET layer_raster = ?
                WHERE layer_id = ?
                ",
                array(json_encode($existingRasterInfo), $dbLayerId)
            );
        }

        $this->getDatabase()->query(
            "UPDATE layer SET layer_type=? WHERE layer_id=?",
            array(json_encode($layerData['layer_type'], JSON_FORCE_OBJECT), $dbLayerId)
        );
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
     * @apiDescription Create a new empty layer
     * @throws Exception
     * @api {POST} /layer/post/:id Post
     * @apiParam {string} name name of the layer
     * @apiParam {string} geotype geotype of the layer
     * @apiSuccess {int} id id of the new layer
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Post(string $name, string $geotype): int
    {
        return (int)$this->getDatabase()->query(
            "INSERT INTO layer (layer_name, layer_geotype) VALUES (?, ?)",
            array($name, $geotype),
            true
        );
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
     * called from MEL, SEL, REL
     * @apiGroup Layer
     * @throws Exception
     * @api {POST} /layer/UpdateRaster
     * @apiParam {string} layer_name Name of the layer the raster image is for.
     * @apiParam {array} raster_bounds 2x2 array of doubles specifying [[min X, min Y], [max X, max Y]]
     * @apiParam {string} image_data Base64 encoded string of image data.
     * @apiDescription UpdateRaster updates raster image
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateRaster(string $layer_name, string $image_data, array $raster_bounds = null): array
    {
        $layerData = $this->getDatabase()->query(
            "SELECT layer_id, layer_raster FROM layer WHERE layer_name = ?",
            array($layer_name)
        );
        if (count($layerData) != 1) {
            throw new Exception("Could not find layer with name " . $layer_name . " to update the raster image");
        }
        $this->log('Updating raster for layer '.$layer_name);

        $rasterData = json_decode($layerData[0]['layer_raster'], true);
        $rasterDataUpdated = false;
        if (empty($rasterData['url'])) {
            throw new Exception("Could not update raster for layer ".$layer_name.
                " raster file name was not specified in raster metadata");
        }

        if (!empty($raster_bounds)) {
            $rasterData['boundingbox'] = $raster_bounds;
            $rasterDataUpdated = true;
        }

        $imageData = base64_decode($image_data);
        Store::EnsureFolderExists(Store::GetRasterStoreFolder($this->getGameSessionId()));
        $f = Store::GetRasterStoreFolder($this->getGameSessionId()).$rasterData['url'];
        if (false ===
            file_put_contents($f, $imageData)) {
            $this->log('Failed to save given image_data to '.$f, self::LOG_LEVEL_ERROR);
        } else {
            $this->log('Stored image_data to file '.$f);
        }

        // Pre-archive the raster file
        Store::EnsureFolderExists(Store::GetRasterArchiveFolder($this->getGameSessionId()));
        $layerPathInfo = pathinfo($rasterData['url']);
        $layerFileName = $layerPathInfo["filename"];
        $layerFileExt = $layerPathInfo["extension"];
        $gameData = $this->getDatabase()->query("SELECT game_currentmonth FROM game")[0];
        $curMonth = intval($gameData['game_currentmonth']);
        $newFileName = $layerFileName."_".$curMonth.".".$layerFileExt;
        $archivedFile = Store::GetRasterArchiveFolder($this->getGameSessionId()).$newFileName;
        if (false ===
            copy($f, $archivedFile)) {
            $this->log(sprintf('Failed to copy %s to file %s for archiving', $f, $archivedFile), self::LOG_LEVEL_ERROR);
        } else {
            $this->log(sprintf('Copied %s to file %s for archiving', $f, $archivedFile));
        }

        if ($rasterDataUpdated) {
            $this->getDatabase()->query(
                "UPDATE layer SET layer_lastupdate=UNIX_TIMESTAMP(NOW(6)), layer_melupdate=1, layer_raster=? ".
                    "WHERE layer_id = ?",
                array(json_encode($rasterData), $layerData[0]['layer_id'])
            );
        } else {
            $this->getDatabase()->query(
                "UPDATE layer SET layer_lastupdate=UNIX_TIMESTAMP(NOW(6)), layer_melupdate=1 WHERE layer_id=?",
                array($layerData[0]['layer_id'])
            );
        }
        $this->log(sprintf(
            'Updated layer record%s with id %d',
            ($rasterDataUpdated ? ' incl. raster data' : ''),
            $layerData[0]['layer_id']
        ));

        return ['logs' => $this->getLogMessages()];
    }

    /**
     * @throws Exception
     */
    public function getExportLayerDescriptions(
        string $workspace,
        string $layer
    ): array {
        $response = $this->geoServer->ows(
            $workspace
            . "/ows?service=WMS&version=1.1.1&request=DescribeLayer&layers="
            . urlencode($workspace)
            . ":"
            . urlencode($layer)
            . "&outputFormat=application/json"
        );
        $data = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() != JSON_ERROR_NONE) {
            $data = [];
            $data["layerDescriptions"] = [];
            $data["error"] = "Failed to decode JSON response from Geoserver. Error: "
                .json_last_error_msg()
                .". Response: "
                .PHP_EOL
                .$response;
        }
        return $data;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetExport(
        string $workspace,
        string $layer,
        string $format = "GML",
        array $layerData = [],
        ?array $rasterMeta = null
    ): ?string {
        //this downloads the data from GeoServer through their REST API
        $maxGMLFeatures = 1000000;
        $layer = str_replace(" ", "%20", $layer);
            
        if ($format == "GML") {
            $url = $workspace . "/ows?service=WFS&version=1.0.0&request=GetFeature&typeName=" . urlencode($workspace) .
                ":" . urlencode($layer) . "&maxFeatures=" . $maxGMLFeatures;
        } elseif ($format == "CSV") {
            $url = $workspace . "/ows?service=WFS&version=1.0.0&outputFormat=csv&request=GetFeature&typeName=" .
                urlencode($workspace) . ":" . urlencode($layer) . "&maxFeatures=" . $maxGMLFeatures;
        } elseif ($format == "JSON") {
            $url = $workspace . "/ows?service=WFS&version=1.0.0&outputFormat=json&request=GetFeature&typeName=" .
                urlencode($workspace) . ":" . urlencode($layer) . "&maxFeatures=" . $maxGMLFeatures;
        } elseif ($format == "PNG") {
            if ($rasterMeta === null) {
                throw new Exception("Tried to export ".$layer." from geoserver in format ".$format.
                    " but rasterMeta was not specified");
            }
            $deltaSizeX = $rasterMeta["boundingbox"][1][0] - $rasterMeta["boundingbox"][0][0];
            $deltaSizeY = $rasterMeta["boundingbox"][1][1] - $rasterMeta["boundingbox"][0][1];
            $widthRatioMultiplier = $deltaSizeX / $deltaSizeY;
                                
            if (empty($layerData['layer_height'])) {
                throw new Exception('Missing required "layer_height" in layer data');
            }

            $height = $layerData['layer_height'];
            $width = $height * $widthRatioMultiplier;
            $bounds = $rasterMeta["boundingbox"][0][0].",".$rasterMeta["boundingbox"][0][1].",".
                $rasterMeta["boundingbox"][1][0].",".$rasterMeta["boundingbox"][1][1];
            $url = $workspace . "/wms/reflect?layers=" . urlencode($workspace) . ":" .
                urlencode($layer) . "&format=image/png&transparent=FALSE&width=" . round($width) . "&height=" .
                $height . "&bbox=".$bounds;
        } else {
            throw new Exception("Incorrect format, use GML, CSV, JSON or PNG");
        }
        return $this->geoServer->ows($url);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ReturnRasterById(int $layer_id = 0): string
    {
        if (empty($layer_id)) {
            throw new Exception("Empty layer_id.");
        }

        $layerData = $this->getDatabase()->query(
            "SELECT layer_id, layer_raster FROM layer WHERE layer_id = ?",
            array($layer_id)
        );
        if (count($layerData) != 1) {
            throw new Exception("Could not find layer with id " . $layer_id . " to request the raster image for");
        }

        $rasterData = json_decode($layerData[0]['layer_raster'], true);
        $filePath = Store::GetRasterStoreFolder($this->getGameSessionId()).$rasterData['url'];
        if (!file_exists($filePath)) {
            throw new Exception("Could not find raster file at path " . $filePath);
        }
        return file_get_contents($filePath);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetMetaForLayerById(int $layerId): array
    {
        $data = $this->getDatabase()->query("SELECT * FROM layer WHERE layer_id=?", array($layerId));
        if (empty($data)) {
            return [];
        }
        Layer::FixupLayerMetaData($data[0]);
        return $data[0];
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function FixupLayerMetaData(array &$data): void
    {
        $data['layer_type'] = json_decode($data['layer_type'], true);
        $data['layer_info_properties'] = (isset($data['layer_info_properties'])) ?
            json_decode($data['layer_info_properties']) : null;
        $data['layer_text_info'] = (isset($data['layer_text_info'])) ? json_decode($data['layer_text_info']) : null;
        $data['layer_tags'] =  (isset($data['layer_tags'])) ? json_decode($data['layer_tags']) : null;
    }
}
