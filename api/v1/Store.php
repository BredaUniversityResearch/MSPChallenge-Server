<?php

namespace App\Domain\API\v1;

use Exception;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use ZipArchive;

class Store extends Base
{
    public GeoServer $geoserver;

    public function __construct(string $method = '')
    {
        parent::__construct($method, []);
        $this->geoserver = new GeoServer();
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
        $result = $this->geoserver->request('workspaces/' . $workspace . '/datastores', 'POST', '<dataStore>
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

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetRasterStoreFolder(int $gameSessionId): string
    {
        $storeFolder = "raster/";
        $storeFolder .= ($gameSessionId != GameSession::INVALID_SESSION_ID) ? $gameSessionId . "/" : "default/";
        return $storeFolder;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetRasterStoreFolderContents(int $gameSessionId): Generator
    {
        // this is a generator function - notice the use of yield to save up on memory use
        try {
            $dirIterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(self::GetRasterStoreFolder($gameSessionId))
            );
        } catch (Exception $e) {
            $dirIterator = array();
        }
        foreach ($dirIterator as $file) {
            if ($file->getFilename() != "." && $file->getFilename() != "..") {
                yield $file->getPathName();
            }
        }
    }

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
        $layer->geoserver->baseurl = $this->geoserver->baseurl;
        $layer->geoserver->username = $this->geoserver->username;
        $layer->geoserver->password = $this->geoserver->password;

        Database::GetInstance()->DBStartTransaction();

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
                Database::GetInstance()->query(
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
                    $json = $layer->GetExport($region, $individualLayer["layerName"], "JSON", [], null);
                    $this->LoadJSON($json, $filename, $region, $layerMetaData);
                }
            } else {
                // Create the metadata for the vector layer, but don't fill the geometry table.
                //   This will be up to the players.
                Database::GetInstance()->query(
                    "INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)",
                    array($filename, $layerMetaData['layer_geotype'], $region)
                );
            }
        }

        $startTime = microtime(true);
        Database::GetInstance()->query(
            "UPDATE geometry SET geometry_persistent=geometry_id WHERE geometry_persistent IS NULL"
        );
        Log::LogDebug(" -> Updated persistent geometry Ids in " . (microtime(true) - $startTime) . " seconds.");
        Database::GetInstance()->DBCommitTransaction();

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
        $result = Database::GetInstance()->query(
            "
            SELECT COUNT(*) as mspIdCount, geometry_mspid
            FROM geometry WHERE geometry_mspid IS NOT NULL GROUP BY geometry_mspid HAVING mspIdCount > 1
            "
        );
        foreach ($result as $duplicateId) {
            $duplicatedLayerNames = Database::GetInstance()->query("SELECT DISTINCT(layer.layer_name) FROM layer 
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
        $metaCheckType = $this->geoserver->request("workspaces/" . $workspace . "/layers/" . $layername);
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
            $meta = $this->geoserver->request("workspaces/" . $workspace . "/coverages/" . $layername . ".json");
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
            $meta = $this->geoserver->request("workspaces/" . $workspace . "/wmslayers/" . $layername);

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
        Database::GetInstance()->query(
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
        $folder = self::GetRasterStoreFolder($gameSessionId);
        self::EnsureFolderExists($folder);
        self::EnsureFolderExists("temp");

        $zip = new ZipArchive;
        $res = $zip->open($raster_zip);
        if ($res === true) {
            $total = $zip->numFiles - 3;
            Log::LogDebug("There are ".$total." raster files in this save, unpacking... This could take a bit longer.");
            $zip->extractTo("temp/");
            $zip->close();
            Log::LogDebug("Now moving all ".$total." raster files to their proper place...");
            rcopy("temp/raster", $folder);
            rrmdir("temp/");
        }
    }

    private function moveDataFromArray(
        array $layerMetaData,
        array &$featureProperties,
        int &$type,
        ?int &$mspId,
        ?int &$countryId
    ): void {

        if (array_key_exists("layer_property_as_type", $layerMetaData) && !empty($layerMetaData["layer_property_as_type"])) {
            // check if the layer_property_as_type value exists in $featureProperties
            if (array_key_exists($layerMetaData["layer_property_as_type"], $featureProperties)) {
                $type = '-1';
                // translate the found $featureProperties value to the type integer using the layer_type > value definition in $layerMetaData
                foreach ($layerMetaData["layer_type"] as $key => $layerTypeMetaData) {
                    // identify the 'other' category
                    if (strtolower($layerTypeMetaData["value"]) == "other") {
                        $keyOther = $key;
                    }
                    if ($layerTypeMetaData["value"] == $featureProperties[$layerMetaData["layer_property_as_type"]]) {
                        $type = $key;
                        break;
                    }
                }
                if ($type == -1) {
                    $type = $keyOther ?? 0;
                }
            }
        } elseif (isset($featureProperties['type']) && is_numeric($featureProperties['type'])) {
            $type = intval($featureProperties['type']);
            unset($featureProperties['type']);
        }

        if (isset($featureProperties['mspid']) && is_numeric($featureProperties['mspid']) && intval($featureProperties['mspid']) !== 0) {
            $mspId = intval($featureProperties['mspid']);
            unset($featureProperties['mspid']);
        }

        if (isset($featureProperties['country_id']) && is_numeric($featureProperties['country_id']) && intval($featureProperties['country_id']) !== 0) {
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
        int $type,
        ?int $mspId,
        string $filename
    ): void {
        $lastId = 0;
        for ($j = 0; $j < sizeof($multi); $j++) {
            if (sizeof($multi) > 1 && $j != 0) {
                //this is a subtractive polygon
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
            if ($geometryData["type"] == "Polygon" || $geometryData["type"] == "LineString" ||  $geometryData["type"] == "Point") {
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
            } elseif (strcasecmp($geometryData["type"], "MultiPoint") == 0) {
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
            } elseif (strcasecmp($geometryData["type"], "MultiLineString") == 0) {
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
            } else {
                throw new Exception(
                    "Encountered unknown feature type ".$geometryData["type"]." in layer ".$filename
                );
            }
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
        $checkExists = Database::GetInstance()->query("SELECT layer_id FROM layer WHERE layer_name = ?", array($layerName));
        if (!empty($checkExists) && isset($checkExists[0]["layer_id"])) {
            return $checkExists[0]["layer_id"];
        }
        return Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)", array($layerName, $layerGeoType, $layerGroup), true);
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
        int $type,
        ?int $mspId,
        int $subtractive = 0,
        string $layerName = ''
    ): int {
        if (IsFeatureFlagEnabled('auto_mspids_by_hash') && $subtractive === 0 && is_null($mspId)) {
             Log::LogDebug(' -> Auto-generating an MSP ID for a bit of geometry in layer ' . $layerName . ' .');
             // so many algorithms to choose from, but this one seemed to have low collision, reasonable speed,
             //   and simply availability to PHP in default installation
             $mspId = hash('fnv1a64', $layerName.$geometry);
        }

        return Database::GetInstance()->query(
            "
            INSERT INTO geometry (
                geometry_layer_id, geometry_geometry, geometry_data, geometry_country_id, geometry_type, geometry_mspid,
                geometry_subtractive
            ) VALUES (?, ?, ?, ?, ?, ?, ?)",
            array($layerId, $geometry, $data, $countryId, $type, $mspId, $subtractive),
            true
        );
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
