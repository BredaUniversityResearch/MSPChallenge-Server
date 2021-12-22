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
    public static function ClearRasterStoreFolder(): void
    {
        self::DeleteDirectory(self::GetRasterStoreFolder());
        self::DeleteDirectory(self::GetRasterArchiveFolder());
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
    public static function GetRasterStoreFolder(): string
    {
        $storeFolder = "raster/";
        $gameSessionId = GameSession::GetGameSessionIdForCurrentRequest();
        $storeFolder .= ($gameSessionId != GameSession::INVALID_SESSION_ID) ? $gameSessionId . "/" : "default/";
        return $storeFolder;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetRasterStoreFolderContents(): Generator
    {
        // this is a generator function - notice the use of yield to save up on memory use
        try {
            $dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(Store::GetRasterStoreFolder()));
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
    public static function GetRasterArchiveFolder(): string
    {
        $folder = self::GetRasterStoreFolder();
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

                self::EnsureFolderExists(self::GetRasterStoreFolder());
                file_put_contents(self::GetRasterStoreFolder() . $filename . ".png", $url);
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
                //download the geometry as a csv file from GeoServer
                if (!IsFeatureFlagEnabled("geoserver_json_importer")) {
                    $startTime = microtime(true);
                    $csv = $layer->GetExport($region, $filename, "CSV");
                    Log::LogDebug(" -> Fetched layer export in " . (microtime(true) - $startTime) . " seconds.");

                    $startTime = microtime(true);
                    $data = array_map("str_getcsv", preg_split('/\r*\n+|\r+/', $csv));
                    Log::LogDebug(" -> Mapped layer export in " . (microtime(true) - $startTime) . " seconds.");

                    $startTime = microtime(true);
                    $this->LoadCSV($data, $filename, $region);
                    Log::LogDebug(" -> Loaded CSV Layer export in " . (microtime(true) - $startTime) . " seconds.");
                } else {
                    $json = $layer->GetExport($region, $filename, "JSON");
                    $this->LoadJSON($json, $filename, $region);
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
    public static function ExtractRasterFilesFromZIP(string $raster_zip): void
    {
        $folder = self::GetRasterStoreFolder();
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
        array &$arr,
        string &$type,
        ?int &$mspId,
        int &$missingMspIds,
        int &$countryId
    ): void {
        $type = '0';
        if (isset($arr['type'])) {
            $type = str_replace(' ', '', $arr['type']);
            unset($arr['type']);

            if ($type == "") {
                $type = '0';
            }
        }

        $mspId = null;
        if (isset($arr['mspid'])) {
            $mspId = $arr['mspid'];
            unset($arr['mspid']);
        } else {
            $missingMspIds++;
        }

        $countryId = null;
        if (isset($arr['country_id'])) {
            $countryId = intval($arr['country_id']);
            if (!is_int($countryId) || $countryId === 0) {
                $countryId = null;
            }
            unset($arr['country_id']);
        }
    }

    /**
     * @throws Exception
     */
    private function insertMultiPolygon(
        array $multi,
        int $layerId,
        string $jsonData,
        int $countryId,
        int $type,
        int $mspId
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
                    $lastId
                );
            } else {
                $lastId = $this->InsertGeometry(
                    $layerId,
                    json_encode($multi[$j]),
                    $jsonData,
                    $countryId,
                    $type,
                    $mspId
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function LoadCSV(array $layerData, string $filename = "", string $datastore = ""): void
    {
        $data = $layerData;

        //store the index for the geometry data
        $geometryColumnIndex = 0;

        $headers = array();

        for ($i = 0; $i < sizeof($data[0]); $i++) {
            if ($data[0][$i] == "the_geom") {
                $geometryColumnIndex = $i;
            }
            array_push($headers, $data[0][$i]);
        }

        //set a geometry type to be saved in the database
        if (strpos($data[1][$geometryColumnIndex], "MULTIPOLYGON") !== false) {
            $type = "polygon";
        } elseif (strpos($data[1][$geometryColumnIndex], "MULTILINESTRING") !== false) {
            $type = "line";
        } elseif (strpos($data[1][$geometryColumnIndex], "POINT") !== false) {
            $type = "point";
        } else {
            if (empty($data[0]) || $data[0][0] == "" || $data[0][0] == " " || empty($data[0][0])) {
                self::Error(
                    "Could not find " . $filename .
                    " on geoserver. Are you sure this file exists on filezilla & geoserver?"
                );
            } else {
                self::Error(
                    "Unknown geometry type found in " . $filename . ". Must be MULTIPOLYGON, MULTILINESTRING or POINT."
                );
            }

            return;
        }

        $layer_id = Database::GetInstance()->query(
            "INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)",
            array($filename, $type, $datastore),
            true
        );

        //used for
        $mspId = null;
        $missingMspIds = 0;

        //Timing in seconds
        $jsonDecodeTotal = 0;
        $insertTimingTotal = 0;

        //start at 1 to skip the header row
        for ($i = 1; $i < sizeof($data); $i++) {
            if (count($data[$i]) <= 1) {
                continue;
            }

            $jsonDecodeStart = microtime(true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::LogDebug(
                    "Json decode failed for geometry id " . $i . ". Error: " . json_last_error_msg() .
                    ". Attempting reformat"
                );
                $geo = $this->ReformatGeoDataFromString($data[$i][$geometryColumnIndex]);
                if ($geo === null) {
                    Log::LogError(
                        "Json reformat failed for geometry id " . $i . ". Error: " . json_last_error_msg() .
                        ". Geometry will not be imported!"
                    );
                    continue;
                }
                $decodedJsonData = json_decode($geo->json, true, 512, JSON_BIGINT_AS_STRING);
            } else {
                Log::LogDebug(
                    "Json decode success for geometry id " . $i . ". Error: " . json_last_error_msg() .
                    ". Attempting reformat"
                );
                continue;
            }

            $arr = array();
            for ($j = 1; $j < sizeof($data[$i]); $j++) {
                if ($j != $geometryColumnIndex) {
                    $arr[$headers[$j]] = $data[$i][$j];
                }
            }

            if (!is_array($decodedJsonData)) {
                Log::LogError(
                    "Failed to import data for geometry " . $geometryColumnIndex .
                    ". Returned data was not an array. Data: " . var_export($decodedJsonData, true)
                );
                continue;
            }

            $jsonDecodeTotal += (microtime(true) - $jsonDecodeStart);

            $this->moveDataFromArray($arr, $type, $mspId, $missingMspIds, $countryId);

            $jsonData = json_encode($arr);

            $insertTimingStart = microtime(true);

            try {
                switch ($geo->type) {
                    case "MULTIPOLYGON":
                        foreach ($decodedJsonData as $multi) {
                            if (!is_array($multi)) {
                                continue;
                            }
                            $this->insertMultiPolygon($multi, $layer_id, $jsonData, $countryId, $type, $mspId);
                        }
                        break;
                    case "POINT":
                        $this->InsertGeometry(
                            $layer_id,
                            json_encode($decodedJsonData),
                            $jsonData,
                            $countryId,
                            $type,
                            $mspId
                        );
                        break;
                    case "MULTILINESTRING":
                        foreach ($decodedJsonData as $line) {
                            $this->InsertGeometry($layer_id, json_encode($line), $jsonData, $countryId, $type, $mspId);
                        }
                        break;
                }
            } catch (Exception $e) {
                Log::LogError($e);
            }

            $insertTimingTotal += (microtime(true) - $insertTimingStart);
        }

        if ($missingMspIds > 0) {
            Log::LogWarning(
                "Encountered " . $missingMspIds . " pieces of geometry that are missing MSP IDs in layer " . $filename
                . ". Please re-export this layer to fix the MSP IDs"
            );
        }
        Log::LogDebug("ImportCSV: Json Decode: " . $jsonDecodeTotal . "s. Insert: " . $insertTimingTotal . "s");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function LoadJSON(string $jsonString, string $filename, string $dataStore): void
    {
        $data = json_decode($jsonString, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new Exception(
                "Failed to decode JSON response from Geoserver. Error: ".json_last_error_msg().". Response: ".PHP_EOL.
                $jsonString
            );
        }

        $layerId = -1;
        $mspId = null;
        $missingMspIds = 0;

        foreach ($data["features"] as $feature) {
            if ($feature["geometry_name"] == "the_geom") {
                $featureProperties = $feature["properties"];

                $this->moveDataFromArray($featureProperties, $type, $mspId, $missingMspIds, $countryId);

                $geometryData = $feature["geometry"];
                if ($geometryData == null) {
                    Log::LogWarning(
                        "Could not import geometry with id ".$feature["id"]." of layer ".$filename.
                        ". Found NULL geometry."
                    );
                    continue;
                }

                if ($layerId === -1) {
                    // First geometry type defines layer type. Would like to pull this out but we need to find the first
                    //   "the_geom" instance.
                    $layerId = Database::GetInstance()->query(
                        "INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)",
                        array($filename, $geometryData["type"], $dataStore),
                        true
                    );
                }
    
                $encodedFeatureData = json_encode($featureProperties);
                if (strcasecmp($geometryData["type"], "MultiPolygon") == 0) {
                    foreach ($geometryData["coordinates"] as $multi) {
                        if (!is_array($multi)) {
                            continue;
                        }
                        $this->insertMultiPolygon($multi, $layerId, $encodedFeatureData, $countryId, $type, $mspId);
                    }
                } elseif (strcasecmp($geometryData["type"], "Point") == 0) {
                    $this->InsertGeometry(
                        $layerId,
                        json_encode(array($geometryData["coordinates"])),
                        $encodedFeatureData,
                        $countryId,
                        $type,
                        $mspId
                    );
                } elseif (strcasecmp($geometryData["type"], "MultiLineString") == 0) {
                    foreach ($geometryData["coordinates"] as $line) {
                        $this->InsertGeometry(
                            $layerId,
                            json_encode($line),
                            $encodedFeatureData,
                            $countryId,
                            $type,
                            $mspId
                        );
                    }
                } else {
                    throw new Exception(
                        "Encountered unknown feature type ".$geometryData["type"]." in layer ".$filename
                    );
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function InsertGeometry(
        int    $layerId,
        string $geometry,
        string $data,
        int    $countryId,
        int    $type,
        int    $mspId,
        int    $subtractive = 0
    ): int {
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
