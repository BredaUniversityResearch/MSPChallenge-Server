<?php

namespace App\Domain\API\v1;

use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
use stdClass;
use ZipArchive;
use function App\rcopy;
use function App\rrmdir;

class Store extends Base
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
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Post(string $workspace, string $name): ?string
    {
        $filetype = ($name == "raster") ? "GeoTIFF" : "shapefile";
        $type = ($name == "raster") ? "GeoTIFF" : "Directory of spatial files (shapefiles)";

        $name = urlencode($name);

        if ($name == "raster") {
            return null;
        }
        $result = $this->geoServer->request('workspaces/' . $workspace . '/datastores', 'POST', '<dataStore>
            <name>' . $name . '</name>
            <type>' . $type . '</type>
            <enabled>true</enabled>
            <connectionParameters>
                <entry key="memory mapped buffer">false</entry>
                <entry key="create spatial index">true</entry>
                <entry key="charset">ISO-8859-1</entry>
                <entry key="filetype">' . $filetype . '</entry>
                <entry key="cache and reuse memory maps">true</entry>
                <entry key="url">file:data/msp/' . $workspace . '/' . $name . '</entry>
                <entry key="namespace">' . $workspace . '</entry>
            </connectionParameters>
            </dataStore>');
        if (false === $result) {
            return null;
        }
        return $result;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ClearRasterStoreFolder(int $gameSessionId): void
    {
        self::DeleteDirectory(self::GetRasterStoreFolder($gameSessionId));
        self::DeleteDirectory(self::GetRasterArchiveFolder($gameSessionId));
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function DeleteDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        self::DeleteDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetRasterStoreFolder(int $gameSessionId): string
    {
        $storeFolder = SymfonyToLegacyHelper::getInstance()->getProjectDir() . "/raster/";
        $storeFolder .= ($gameSessionId != GameSession::INVALID_SESSION_ID) ? $gameSessionId . "/" : "default/";
        return $storeFolder;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetRasterArchiveFolder(int $gameSessionId): string
    {
        $folder = self::GetRasterStoreFolder($gameSessionId);
        return $folder . "archive/";
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function EnsureFolderExists(string $directory): void
    {
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CreateLayer(array $layerMetaData, string $region): void
    {
        //download the geometry (or raster) data from GeoServer & create layers in the database based on the config file
        $layer = new Layer();
        $layer->getGeoServer()
            ->setBaseurl($this->geoServer->baseurl)
            ->setUsername($this->geoServer->username)
            ->setPassword($this->geoServer->password);

        $this->getDatabase()->DBStartTransaction();

        $filename = $layerMetaData['layer_name'];

        //raster layers are loaded differently
        if ($layerMetaData['layer_geotype'] == "raster") {
            // Check if we should download the raster layer from geoserver. Default behaviour says we do, and just don't
            //   do it when it is specified.
            if (!array_key_exists("layer_download_from_geoserver", $layerMetaData) ||
                $layerMetaData["layer_download_from_geoserver"] == true
            ) {
                //download the image to the /raster folder from GeoServer
                $rasterMeta = $this->CreateRasterMeta($region, $filename);

                $url = $layer->GetExport($region, $filename, "PNG", $layerMetaData, $rasterMeta);

                self::EnsureFolderExists(self::GetRasterStoreFolder($this->getGameSessionId()));
                file_put_contents(self::GetRasterStoreFolder($this->getGameSessionId()) . $filename . ".png", $url);
            } else {
                // Create the metadata for the raster layer, but don't fill in the layer_raster field.
                //   This must be done by something else later.
                $this->getDatabase()->query(
                    "INSERT INTO layer (layer_name, layer_geotype, layer_group, layer_editable) VALUES (?, ?, ?, ?)",
                    array($filename, "raster", $region, 0)
                );
            }
        } else {
            // Check if we should download the vector layer from geoserver. Default behaviour says we do, and just
            //   don't do it when it is specified.
            if (!array_key_exists("layer_download_from_geoserver", $layerMetaData) ||
                $layerMetaData["layer_download_from_geoserver"] == true
            ) {
                $layerDescriptionRequest = $layer->getExportLayerDescriptions($region, $filename);
                if (isset($layerDescriptionRequest["error"])) {
                    Log::LogError($layerDescriptionRequest["error"]);
                }
                foreach ($layerDescriptionRequest["layerDescriptions"] as $individualLayer) {
                    $json = $layer->GetExport($region, $individualLayer["layerName"], "JSON");
                    $layerMetaData['original_layer_name'] = $individualLayer["layerName"];
                    $this->LoadJSON($json, $filename, $region, $layerMetaData);
                }
            } else {
                // Create the metadata for the vector layer, but don't fill the geometry table.
                //   This will be up to the players.
                $this->getDatabase()->query(
                    "INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)",
                    array($filename, $layerMetaData['layer_geotype'], $region)
                );
            }
        }

        $startTime = microtime(true);
        $this->getDatabase()->query(
            "UPDATE geometry SET geometry_persistent=geometry_id WHERE geometry_persistent IS NULL"
        );
        Log::LogDebug(" -> Updated persistent geometry Ids in " . (microtime(true) - $startTime) . " seconds.");
        $this->getDatabase()->DBCommitTransaction();

        $startTime = microtime(true);
        $this->CheckForDuplicateMspIds();
        Log::LogDebug(" -> Checked layer MSP IDs in " . (microtime(true) - $startTime) . " seconds.");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function CheckForDuplicateMspIds(): void
    {
        $result = $this->getDatabase()->query(
            "
            SELECT COUNT(*) as mspIdCount, geometry_mspid
            FROM geometry WHERE geometry_mspid IS NOT NULL GROUP BY geometry_mspid HAVING mspIdCount > 1
            "
        );
        foreach ($result as $duplicateId) {
            $duplicatedLayerNames = $this->getDatabase()->query("SELECT DISTINCT(layer.layer_name) FROM layer 
					INNER JOIN geometry ON geometry.geometry_layer_id = layer.layer_id
				WHERE geometry.geometry_mspid = ?", array($duplicateId['geometry_mspid']));
            $layerNames = implode(", ", array_map(function ($data) {
                return $data['layer_name'];
            }, $duplicatedLayerNames));
            Log::LogError(
                "Found MSP ID " . $duplicateId['geometry_mspid'] . " which was duplicated " .
                $duplicateId['mspIdCount'] . " times in the following layers: " . $layerNames
            );
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function CreateRasterMeta(string $workspace, string $layername): array
    {
        $metaCheckType = $this->geoServer->request("workspaces/" . $workspace . "/layers/" . $layername);
        try {
            $returnMetaCheckType = json_decode($metaCheckType);
            if (!isset($returnMetaCheckType->layer->type)) {
                throw new Exception(
                    "Layer type (local raster store or WMS store) could not be ascertained, so cannot continue with ".
                    $layername
                );
            }
            $type = $returnMetaCheckType->layer->type;
        } catch (Exception $e) {
            throw new Exception(
                "Layer type (local raster store or WMS store) could not be ascertained, so cannot continue with ".
                $layername
            );
        }

        if ($type == "RASTER") {
            $meta = $this->geoServer->request("workspaces/" . $workspace . "/coverages/" . $layername . ".json");
            try {
                $data = json_decode($meta);
                if (!isset($data->coverage)) {
                    throw new Exception("Layer ".$layername." (raster) could not be downloaded.");
                }
                $bb = $data->coverage->nativeBoundingBox;
            } catch (Exception $e) {
                // if this triggers then either the GeoServer is down, could not be reached for some other reason,
                //   or has been set up wrongly for this code
                throw new Exception(
                    "Something went wrong downloading " . $layername .
                    " (raster). Is the file on GeoServer? Should download be attempted at all?. Exception message: " .
                    $e->getMessage()
                );
            }
        } elseif ($type == "WMS") {
            $meta = $this->geoServer->request("workspaces/" . $workspace . "/wmslayers/" . $layername);

            try {
                $data = json_decode($meta);
                if (!isset($data->wmsLayer)) {
                    throw new Exception("Layer ".$layername." (WMS) could not be downloaded.");
                }
                $bb = $data->wmsLayer->nativeBoundingBox;
            } catch (Exception $e) {
                // if this triggers then either the GeoServer is down, could not be reached for some other reason,
                //   or has been set up wrongly for this code
                throw new Exception(
                    "Something went wrong downloading " . $layername .
                    " (WMS). Is the file on GeoServer? Should download be attempted at all?. Exception message: " .
                    $e->getMessage()
                );
            }
        } else {
            throw new Exception(
                "Layer ".$layername.
                " returned a type that's not supported at this time. Only raster and WMS are supported"
            );
        }
        
        $rasterMeta = array(
            "url" => $layername . ".png", "boundingbox" => array(array($bb->minx, $bb->miny),
            array($bb->maxx, $bb->maxy))
        );
        $this->getDatabase()->query(
            "
            INSERT INTO layer (
                layer_name, layer_raster, layer_geotype, layer_group, layer_editable
            ) VALUES (?, ?, ?, ?, ?)
            ",
            array($layername, json_encode($rasterMeta), "raster", $workspace, 0)
        );
        return $rasterMeta;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ExtractRasterFilesFromZIP(string $raster_zip, int $gameSessionId): void
    {
        $tempStore = self::GetRasterStoreFolder(-1);
        $folder = self::GetRasterStoreFolder($gameSessionId);
        self::EnsureFolderExists($folder);
        try {
            $zip = new ZipArchive;
            $res = $zip->open($raster_zip);
            if ($res === true) {
                Log::LogDebug("There are raster files in this save, unpacking... This could take a bit longer.");
                if (!$zip->extractTo($tempStore)) {
                    throw new \Exception('ExtractTo failed.');
                } else {
                    Log::LogDebug('ExtractTo succeeded.');
                }
                $zip->close();
                Log::LogDebug("Now moving all raster files to their proper place...");
                rcopy($tempStore."raster", $folder);
                rrmdir($tempStore);
            }
        } catch (\Exception $e) {
            Log::LogError($e->getMessage().PHP_EOL.$e->getTraceAsString());
        }
    }

    private function moveDataFromArray(
        array $layerMetaData,
        array &$featureProperties,
        string &$type,
        ?int &$mspId,
        ?int &$countryId
    ): void {
        if (!empty($layerMetaData['original_layer_name'])) {
            $featureProperties['original_layer_name'] = $layerMetaData['original_layer_name'];
        }

        if (!empty($layerMetaData["layer_property_as_type"])) {
            // check if the layer_property_as_type value exists in $featureProperties
            $type = '-1';
            if (!empty($featureProperties[$layerMetaData["layer_property_as_type"]])) {
                $featureTypeProperty = $featureProperties[$layerMetaData["layer_property_as_type"]];
                foreach ($layerMetaData["layer_type"] as $typeValue => $layerTypeMetaData) {
                    if (empty($layerTypeMetaData["map_type"])) {
                        continue;
                    }
                    // identify the 'other' category
                    if (strtolower($layerTypeMetaData["map_type"]) == "other") {
                        $typeOther = $typeValue;
                    }
                    // if the map_type is a range, check if the featureTypeProperty is within that range
                    if (str_contains($layerTypeMetaData["map_type"], '-')) {
                        $typeValues = explode('-', $layerTypeMetaData["map_type"], 2);
                        // a range of minimum to maximum (but not including) integer or float values
                        if (is_numeric($typeValues[0]) && is_numeric($typeValues[1]) &&
                            (float) $featureTypeProperty >= (float) $typeValues[0]
                            && (float) $featureTypeProperty < (float) $typeValues[1]) {
                            $type = $typeValue;
                            break;
                        }
                    }
                    // check if the featureTypeProperty matches the map_type value
                    if ($layerTypeMetaData["map_type"] == $featureTypeProperty) {
                        // translate the found $featureProperties value to the type value
                        // can be integer, float, string
                        $type = $typeValue;
                        break;
                    }
                }
            }
            if ($type == -1) {
                $type = $typeOther ?? 0;
            }
        } else {
            $type = (int)($featureProperties['type'] ?? 0);
            unset($featureProperties['type']);
        }


        if (isset($featureProperties['mspid'])
            && is_numeric($featureProperties['mspid'])
            && intval($featureProperties['mspid']) !== 0
        ) {
            $mspId = intval($featureProperties['mspid']);
            unset($featureProperties['mspid']);
        }

        if (isset($featureProperties['country_id'])
            && is_numeric($featureProperties['country_id'])
            && intval($featureProperties['country_id']) !== 0
        ) {
            $countryId = intval($featureProperties['country_id']);
            unset($featureProperties['country_id']);
        }
    }

    /**
     * @throws Exception
     */
    private function insertMultiPolygon(
        array $multi,
        int $layerId,
        string $jsonData,
        ?int $countryId,
        string $type,
        ?int $mspId,
        string $filename
    ): void {
        $lastId = 0;
        for ($j = 0; $j < sizeof($multi); $j++) {
            if (sizeof($multi) > 1 && $j != 0) {
                //this is a subtractive polygon
                if ($lastId > 0) {
                    $this->InsertGeometry(
                        $layerId,
                        json_encode($multi[$j]),
                        $jsonData,
                        $countryId,
                        $type,
                        null,
                        $lastId,
                        $filename
                    );
                }
            } else {
                $lastId = $this->InsertGeometry(
                    $layerId,
                    json_encode($multi[$j]),
                    $jsonData,
                    $countryId,
                    $type,
                    $mspId,
                    0,
                    $filename
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function LoadJSON(string $jsonString, string $filename, string $dataStore, array $layerMetaData): void
    {
        $data = json_decode($jsonString, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new Exception(
                "Failed to decode JSON response from Geoserver. Error: ".json_last_error_msg().". Response: ".PHP_EOL.
                $jsonString
            );
        }

        foreach ($data["features"] as $feature) {
            $featureProperties = $feature["properties"];

            $type = '0';
            $mspId = null;
            $countryId = null;
            $this->moveDataFromArray($layerMetaData, $featureProperties, $type, $mspId, $countryId);

            $geometryData = $feature["geometry"];
            if ($geometryData == null) {
                Log::LogWarning(
                    "Could not import geometry with id ".$feature["id"]." of layer ".$filename.
                    ". The feature in question has NULL geometry. Here's some property information to help find ".
                    "the feature: MSP ID: ".$mspId." - ".substr(var_export($featureProperties, true), 0, 80)
                );
                continue;
            }

            $layerId = $this->getLayerId($filename, $geometryData["type"], $dataStore);

            // let's make sure we are always working with multidata: multipolygon, multilinestring, multipoint
            if ($geometryData["type"] == "Polygon"
                || $geometryData["type"] == "LineString"
                ||  $geometryData["type"] == "Point"
            ) {
                $geometryData["coordinates"] = [$geometryData["coordinates"]];
                $geometryData["type"] = "Multi".$geometryData["type"];
            }

            $encodedFeatureData = json_encode($featureProperties);
            if (strcasecmp($geometryData["type"], "MultiPolygon") == 0) {
                foreach ($geometryData["coordinates"] as $multi) {
                    if (!is_array($multi)) {
                        continue;
                    }
                    $this->insertMultiPolygon(
                        $multi,
                        $layerId,
                        $encodedFeatureData,
                        $countryId,
                        $type,
                        $mspId,
                        $filename
                    );
                }
                continue;
            }
            if (strcasecmp($geometryData["type"], "MultiPoint") == 0) {
                $this->InsertGeometry(
                    $layerId,
                    json_encode($geometryData["coordinates"]),
                    $encodedFeatureData,
                    $countryId,
                    $type,
                    $mspId,
                    0,
                    $filename
                );
                continue;
            }
            if (strcasecmp($geometryData["type"], "MultiLineString") == 0) {
                foreach ($geometryData["coordinates"] as $line) {
                    $this->InsertGeometry(
                        $layerId,
                        json_encode($line),
                        $encodedFeatureData,
                        $countryId,
                        $type,
                        $mspId,
                        0,
                        $filename
                    );
                }
                continue;
            }
            throw new Exception(
                "Encountered unknown feature type ".$geometryData["type"]." in layer ".$filename
            );
        }
    }

    /**
     * @throws Exception
     */
    private function getLayerId(
        string $layerName,
        string $layerGeoType,
        string $layerGroup
    ): int {
        $checkExists = $this->getDatabase()->query(
            "SELECT layer_id FROM layer WHERE layer_name = ?",
            array($layerName)
        );
        if ((int)($checkExists[0]["layer_id"] ?? 0) === 0) {
            return (int)$this->getDatabase()->query(
                "INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)",
                array($layerName, $layerGeoType, $layerGroup),
                true
            );
        }
        return (int)$checkExists[0]["layer_id"];
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function InsertGeometry(
        int $layerId,
        string $geometry,
        string $data,
        ?int $countryId,
        string $type,
        ?int $mspId,
        int $subtractive = 0,
        string $layerName = ''
    ): int {
        if ($subtractive === 0 && is_null($mspId)) {
            Log::LogDebug(' -> Auto-generating an MSP ID for a bit of geometry in layer ' . $layerName . ' .');
            // so many algorithms to choose from, but this one seemed to have low collision, reasonable speed,
            //   and simply availability to PHP in default installation
            $algo = 'fnv1a64';
            // to avoid duplicate MSP IDs, we need the string to include the layer name, the geometry, and if available
            //   the geometry's name ... there have been cases in which one layer had exactly the same geometry twice
            //   to indicate two different names given to that area... very annoying
            $dataToHash = $layerName.$geometry;
            $dataArray = json_decode($data, true);
            $dataToHash .= $dataArray['name'] ?? $dataArray['sitename'] ?? '';
            $mspId = hash($algo, $dataToHash);
        }

        try {
            return (int)$this->getDatabase()->query(
                "INSERT INTO geometry (
                    geometry_layer_id, geometry_geometry, geometry_data, geometry_country_id, geometry_type,
                     geometry_mspid, geometry_subtractive
                ) VALUES (?, ?, ?, ?, ?, ?, ?)",
                array($layerId, $geometry, $data, $countryId, $type, $mspId, $subtractive),
                true
            );
        } catch (Exception $e) {
            if ($e->getCode() == 23000) {
                // geometry table's constraint on unique combination of coordinates and feature data was violated
                Log::LogDebug(
                    ' -> Note: geometry not added, as its coordinates and feature dataset were already in the database.'
                    ." The geometry concerned was in layer {$layerName} and the feature dataset starts with "
                    .substr($data, 0, 30)
                );
                return 0;
            }
            throw $e;
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ReformatGeoDataFromString(string $data): ?object
    {
        try {
            if (strpos($data, "MULTIPOLYGON EMPTY") !== false) {
                return null;
            } elseif (strpos($data, "MULTIPOLYGON") !== false) {
                $str = "(((" . explode("(((", $data)[1];
                $str = str_replace("), (", "]],[[", $str);
                $str = str_replace(")", "]", $str);
                $str = str_replace("(", "[", $str);
                $str = str_replace(", ", "],[", $str);
                return $this->GeoObject("[" . str_replace(" ", ",", $str) . "]", "MULTIPOLYGON");
            } elseif (strpos($data, "MULTILINESTRING") !== false) {
                $str = "((" . explode("((", $data)[1];
                $str = str_replace("), (", "],[", $str);
                $str = str_replace(")", "]", $str);
                $str = str_replace("(", "[", $str);
                $str = str_replace(", ", "],[", $str);
                return $this->GeoObject("[" . str_replace(" ", ",", $str) . "]", "MULTILINESTRING");
            } elseif (strpos($data, "MULTIPOINT") !== false) {
                $str = str_replace("MULTIPOINT ((", "", $data);
                $str = str_replace("))", "", $str);
                return $this->GeoObject("[[" . str_replace(" ", ",", $str) . "]]", "POINT");
            } elseif (strpos($data, "POINT") !== false) {
                $str = str_replace("POINT (", "", $data);
                $str = str_replace(")", "", $str);
                return $this->GeoObject("[[" . str_replace(" ", ",", $str) . "]]", "POINT");
            }
        } catch (Exception $e) {
            self::Debug($e);
            self::Debug($data);
        }

        return null;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GeoObject(string $str, string $type): object
    {
        $struct = new StdClass();
        $struct->json = $str;
        $struct->type = $type;
        return $struct;
    }
}
