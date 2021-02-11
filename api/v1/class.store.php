<?php
class Store extends Base
{
	private $csvdir = "files/csv/";

	public $geoserver;

	protected $allowed = array();

	public function __construct($method = "")
	{
		parent::__construct($method);
		$this->geoserver = new GeoServer();
	}

	public function Post($workspace, $name)
	{
		$filetype = ($name == "raster") ? "GeoTIFF" : "shapefile";
		$type = ($name == "raster") ? "GeoTIFF" : "Directory of spatial files (shapefiles)";

		$name = urlencode($name);

		if ($name == "raster") {
			return;
		} else {
			return $this->geoserver->request('workspaces/' . $workspace . '/datastores', 'POST', '<dataStore>
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
		}
	}

	public function GenerateStores($dirs)
	{
		foreach ($dirs as $workspace => $subdirs) {
			foreach ($subdirs as $datastore => $subdir) {
				$fileParts = pathinfo($datastore);
				if (isset($fileParts['extension']) && $fileParts['extension'] == "json") {
					continue;
				}
				$this->Post($workspace, $datastore);
			}
		}
	}

	public function DeleteWorkspaces($dirs)
	{
		//recursively delete workspaces, also deletes stores & layers
		foreach ($dirs as $workspace => $subdirs) {
			$this->geoserver->request('workspaces/' . $workspace . "?recurse=true", "DELETE");
		}
	}

	public function DeleteWorkspace($workspace)
	{
		$this->geoserver->request('workspaces/' . $workspace . "?recurse=true", "DELETE");
	}

	public function GenerateWorkspaces($dirs)
	{
		foreach ($dirs as $workspace => $subdirs) {
			$this->geoserver->request('workspaces', 'POST', '<workspace><name>' . $workspace . '</name></workspace>');
		}
	}

	public function GenerateWorkspace($name)
	{
		$this->geoserver->request('workspaces', 'POST', '<workspace><name>' . $name . '</name></workspace>');
	}

	public function ExportGeoserver($dirs)
	{
		$layer = new Layer("");
		foreach ($dirs as $key => $subdirs) {
			foreach ($subdirs as $subdir) {
				$layers = $layer->GetLayers($key, $subdir);
				if ($layers != null) {
					foreach ($layers as $l) {
						$csv = $layer->GetExport($key, $l->name, "CSV");
						$filename = $this->SaveCSV($l->name, $csv);
					}
				}
			}
		}
	}

	public static function ClearRasterStoreFolder()
	{
		self::DeleteDirectory(self::GetRasterStoreFolder());
		self::DeleteDirectory(self::GetRasterArchiveFolder());
	}

	private static function DeleteDirectory($dir)
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

	public static function GetRasterStoreFolder()
	{
		$storeFolder = "raster/";
		$gameSessionId = GameSession::GetGameSessionIdForCurrentRequest();
		$storeFolder .= ($gameSessionId != GameSession::INVALID_SESSION_ID) ? $gameSessionId . "/" : "default/";
		return $storeFolder;
	}

	public static function GetRasterArchiveFolder()
	{
		$folder = self::GetRasterStoreFolder();
		return $folder . "archive/";
	}

	public static function EnsureFolderExists($directory)
	{
		if (!file_exists($directory)) {
			mkdir($directory, 0777, true);
		}
	}

	public function CreateLayer($layerMetaData, $region)
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
			//Check if we should download the raster layer from geoserver. Default behaviour says we do, and just don't do it when it is specified.
			if (!array_key_exists("layer_download_from_geoserver", $layerMetaData) || $layerMetaData["layer_download_from_geoserver"] == true) {
				//download the image to the /raster folder from GeoServer
				$rasterMeta = $this->CreateRasterMeta($region, $filename);

				$url = $layer->GetExport($region, $filename, "PNG", $layerMetaData, $rasterMeta);

				self::EnsureFolderExists(self::GetRasterStoreFolder());
				file_put_contents(self::GetRasterStoreFolder() . $filename . ".png", $url);
			} else {
				//Create the metadata for the raster layer, but don't fill in the layer_raster field. This must be done by something else later.
				Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_geotype, layer_group, layer_editable) VALUES (?, ?, ?, ?)", array($filename, "raster", $region, 0));
			}
		} else {
			//Check if we should download the vector layer from geoserver. Default behaviour says we do, and just don't do it when it is specified.
			if (!array_key_exists("layer_download_from_geoserver", $layerMetaData) || $layerMetaData["layer_download_from_geoserver"] == true) {
				//download the geometry as a csv file from GeoServer
				if (!IsFeatureFlagEnabled("geoserver_json_importer"))
				{
					$startTime = microtime(true);
					$csv = $layer->GetExport($region, $filename, "CSV", "", null);
					Log::LogDebug(" -> Fetched layer export in " . (microtime(true) - $startTime) . " seconds.");

					$startTime = microtime(true);
					$data = array_map("str_getcsv", preg_split('/\r*\n+|\r+/', $csv));
					Log::LogDebug(" -> Mapped layer export in " . (microtime(true) - $startTime) . " seconds.");

					$startTime = microtime(true);
					$this->LoadCSV($data, $filename, $region);
					Log::LogDebug(" -> Loaded CSV Layer export in " . (microtime(true) - $startTime) . " seconds.");
				}
				else
				{
					$json = $layer->GetExport($region, $filename, "JSON", "", null);
					$this->LoadJSON($json, $filename, $region);
				}

			} else {
				//Create the metadata for the vector layer, but don't fill the geometry table. This will be up to the players.
				Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)", array($filename, $layerMetaData['layer_geotype'], $region));
			}
		}

		$startTime = microtime(true);
		Database::GetInstance()->query("UPDATE geometry SET geometry_persistent=geometry_id WHERE geometry_persistent IS NULL");
		Log::LogDebug(" -> Updated persistent geometry Ids in " . (microtime(true) - $startTime) . " seconds.");
		Database::GetInstance()->DBCommitTransaction();

		$startTime = microtime(true);
		$this->CheckForDuplicateMspIds();
		Log::LogDebug(" -> Checked layer MSP IDs in " . (microtime(true) - $startTime) . " seconds.");
	}

	private function CheckForDuplicateMspIds()
	{
		$result = Database::GetInstance()->query("SELECT COUNT(*) as mspIdCount, geometry_mspid FROM geometry WHERE geometry_mspid IS NOT NULL GROUP BY geometry_mspid HAVING mspIdCount > 1");
		foreach ($result as $duplicateId) {
			$duplicatedLayerNames = Database::GetInstance()->query("SELECT DISTINCT(layer.layer_name) FROM layer 
					INNER JOIN geometry ON geometry.geometry_layer_id = layer.layer_id
				WHERE geometry.geometry_mspid = ?", array($duplicateId['geometry_mspid']));
			$layerNames = implode(", ", array_map(function ($data) {
				return $data['layer_name'];
			}, $duplicatedLayerNames));
			Log::LogError("Found MSP ID " . $duplicateId['geometry_mspid'] . " which was duplicated " . $duplicateId['mspIdCount'] . " times in the following layers: " . $layerNames);
		}
	}

	private function CreateRasterMeta($workspace, $filename)
	{
		$meta = $this->geoserver->request("workspaces/" . $workspace . "/coveragestores/" . $filename . "/coverages/" . $filename . ".json");

		try {
			$data = json_decode($meta);

			if (!isset($data->coverage))
				throw new Exception("Layer could not be downloaded.");

			$bb = $data->coverage->nativeBoundingBox;

			$rasterMeta = array("url" => $filename . ".png", "boundingbox" => array(array($bb->minx, $bb->miny), array($bb->maxx, $bb->maxy)));

			$q = Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_raster, layer_geotype, layer_group, layer_editable) VALUES (?, ?, ?, ?, ?)", array($filename, json_encode($rasterMeta), "raster", $workspace, 0));

			return $rasterMeta;
		} catch (Exception $e) {
			//if this triggers something went horribly wrong. Probably means that Geoserver is down or broken in some way
			throw new Exception("Something went wrong downloading " . $filename . ". Is the file on GeoServer? Should download be attempted at all?. Exception message: " . $e->getMessage());
			//Base::Debug($e->getMessage()." - on line ".$e->getLine()." of file ".$e->getFile());
		}
	}

	private function LoadCSV($layerdata, $filename = "", $datastore = "")
	{
		$data = $layerdata;

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
		$type = "";
		if (strpos($data[1][$geometryColumnIndex], "MULTIPOLYGON") !== false) {
			$type = "polygon";
		} elseif (strpos($data[1][$geometryColumnIndex], "MULTILINESTRING") !== false) {
			$type = "line";
		} elseif (strpos($data[1][$geometryColumnIndex], "POINT") !== false) {
			$type = "point";
		} else {
			if (empty($data[0]) || $data[0][0] == "" || $data[0][0] == " " || empty($data[0][0])) {
				Base::Error("Could not find " . $filename . " on geoserver. Are you sure this file exists on filezilla & geoserver?");
			} else {
				Base::Error("Unknown geometry type found in " . $filename . ". Must be MULTIPOLYGON, MULTILINESTRING or POINT.");
			}

			return;
		}

		$layer_id = Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)", array($filename, $type, $datastore), true);

		//used for 
		$lastid = 0;
		$missingMspIds = 0;

		//Timing in seconds
		$jsonDecodeTotal = 0;
		$insertTimingTotal = 0;

		//start at 1 to skip the header row
		for ($i = 1; $i < sizeof($data); $i++) {
			if (count($data[$i]) <= 1) continue;

			$jsonDecodeStart = microtime(true);
			$decodedJsonData = json_decode($data[$i][$geometryColumnIndex], true, 512, JSON_BIGINT_AS_STRING);

			if (json_last_error() !== JSON_ERROR_NONE) {
				Log::LogDebug("Json decode failed for geometry id " . $i . ". Error: " . json_last_error_msg() . ". Attempting reformat");
				$geo = $this->ReformatGeoDataFromString($data[$i][$geometryColumnIndex]);
				if ($geo === false) {
					Log::LogError("Json reformat failed for geometry id " . $i . ". Error: " . json_last_error_msg() . ". Geometry will not be imported!");
					continue;
				}
				$decodedJsonData = json_decode($geo->json, true, 512, JSON_BIGINT_AS_STRING);
			} else {
				Log::LogDebug("Json decode success for geometry id " . $i . ". Error: " . json_last_error_msg() . ". Attempting reformat");
			}

			$arr = array();
			for ($j = 1; $j < sizeof($data[$i]); $j++) {
				if ($j != $geometryColumnIndex) {
					$arr[$headers[$j]] = $data[$i][$j];
				}
			}

			if (!is_array($decodedJsonData)) {
				Log::LogError("Failed to import data for geometry " . $geometryColumnIndex . ". Returned data was not an array. Data: " . var_export($decodedJsonData, true));
				continue;
			}

			$jsonDecodeTotal += (microtime(true) - $jsonDecodeStart);

			if (isset($arr['type'])) {
				$type = str_replace(' ', '', $arr['type']);
				unset($arr['type']);

				if ($type == "")
					$type = '0';
			} else {
				$type = '0';
			}

			$mspid = null;

			if (isset($arr['mspid'])) {
				$mspid = $arr['mspid'];
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

			$jsondata = json_encode($arr);

			$insertTimingStart = microtime(true);

			try {
				switch ($geo->type) {
					case "MULTIPOLYGON":
						foreach ($decodedJsonData as $multi) {
							if (is_array($multi)) {
								for ($j = 0; $j < sizeof($multi); $j++) {
									if (sizeof($multi) > 1 && $j != 0) {
										//this is a subtractive polygon
										$this->InsertGeometry($layer_id, json_encode($multi[$j]), $jsondata, $countryId, $type, null, $lastid);
									} else {
										$lastid = $this->InsertGeometry($layer_id, json_encode($multi[$j]), $jsondata, $countryId, $type, $mspid);
									}
								}
							}
						}
						break;
					case "POINT":
						$this->InsertGeometry($layer_id, json_encode($decodedJsonData), $jsondata, $countryId, $type, $mspid);
						break;
					case "MULTILINESTRING":
						foreach ($decodedJsonData as $line) {
							$this->InsertGeometry($layer_id, json_encode($line), $jsondata, $countryId, $type, $mspid);
						}
						break;
				}
			} catch (Exception $e) {
				Log::LogError($e);
			}

			$insertTimingTotal += (microtime(true) - $insertTimingStart);
		}

		if ($missingMspIds > 0) {
			Log::LogWarning("Encountered " . $missingMspIds . " pieces of geometry that are missing MSP IDs in layer " . $filename . ". Please re-export this layer to fix the MSP IDs");
		}
		Log::LogDebug("        ImportCSV: Json Decode: " . $jsonDecodeTotal . "s. Insert: " . $insertTimingTotal . "s");
	}

	private function LoadJSON(string $jsonString, string $filename, string $dataStore)
	{
		$data = json_decode($jsonString, true, 512, JSON_BIGINT_AS_STRING);
		if (json_last_error() != JSON_ERROR_NONE)
		{
			throw new Exception("Failed to decode JSON response from Geoserver. Error: ".json_last_error_msg().". Response: ".PHP_EOL.$jsonString);
		}

		$layerId = -1;
		$missingMspIds = 0;
		$lastId = 0;

		foreach($data["features"] as $feature)
		{
			if ($feature["geometry_name"] == "the_geom")
			{
				$featureProperties = $feature["properties"];
				if (isset($featureProperties['type'])) {
					$type = str_replace(' ', '', $featureProperties['type']);
					unset($featureProperties['type']);
	
					if ($type == "")
						$type = '0';
				} else {
					$type = '0';
				}
	
				$mspid = null;
				if (isset($featureProperties['mspid'])) {
					$mspid = $featureProperties['mspid'];
					unset($featureProperties['mspid']);
				} else {
					$missingMspIds++;
				}
	
				$countryId = null;
				if (isset($featureProperties['country_id'])) {
					$countryId = intval($featureProperties['country_id']);
					if (!is_int($countryId) || $countryId === 0) {
						$countryId = null;
					}
					unset($featureProperties['country_id']);
				}

				$geometryData = $feature["geometry"];
				if ($geometryData == null)
				{
					Log::LogWarning("Could not import geometry with id ".$feature["id"]." of layer ".$filename.". Found NULL geometry.");
					continue;
				}

				if ($layerId === -1)
				{
					//First geometry type defines layer type. Would like to pull this out but we need to find the first "the_geom" instance.
					$layerId = Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)", array($filename, $geometryData["type"], $dataStore), true);
				}
	
				$encodedFeatureData = json_encode($featureProperties);
				if (strcasecmp ($geometryData["type"], "MultiPolygon") == 0)
				{
					foreach ($geometryData["coordinates"] as $multi) 
					{
						if (is_array($multi)) 
						{
							for ($j = 0; $j < sizeof($multi); $j++) 
							{
								if (sizeof($multi) > 1 && $j != 0) 
								{
									//this is a subtractive polygon
									$this->InsertGeometry($layerId, json_encode($multi[$j]), $encodedFeatureData, $countryId, $type, null, $lastId);
								} else 
								{
									$lastId = $this->InsertGeometry($layerId, json_encode($multi[$j]), $encodedFeatureData, $countryId, $type, $mspid);
								}
							}
						}
					}
				}
				else if (strcasecmp ($geometryData["type"], "Point") == 0)
				{
					$this->InsertGeometry($layerId, json_encode(array($geometryData["coordinates"])), $encodedFeatureData, $countryId, $type, $mspid);
				}
				else if (strcasecmp ($geometryData["type"], "MultiLineString") == 0)
				{
					foreach ($geometryData["coordinates"] as $line) {
						$this->InsertGeometry($layerId, json_encode($line), $encodedFeatureData, $countryId, $type, $mspid);
					}
				}
				else 
				{
					throw new Exception("Encountered unknown feature type ".$geometryData["type"]." in layer ".$filename);
				}
			}
		}

	}
	
	private function InsertGeometry($layerid, $geometry, $data, $countryId, $type, $mspid, $subtractive = 0)
	{
		$persistentid = Database::GetInstance()->query(
			"INSERT INTO geometry (geometry_layer_id, geometry_geometry, geometry_data, geometry_country_id, geometry_type, geometry_mspid, geometry_subtractive) 
								VALUES (?, ?, ?, ?, ?, ?, ?)",
			array($layerid, $geometry, $data, $countryId, $type, $mspid, $subtractive),
			true
		);

		return $persistentid;
	}

	public function ReformatGeoDataFromString($data)
	{
		try {
			if (strpos($data, "MULTIPOLYGON EMPTY") !== false) {
				return false;
			} else if (strpos($data, "MULTIPOLYGON") !== false) {
				$str = "(((" . explode("(((", $data)[1];
				$str = str_replace("), (", "]],[[", $str);
				$str = str_replace(")", "]", $str);
				$str = str_replace("(", "[", $str);
				$str = str_replace(", ", "],[", $str);
				return $this->GeoObject("[" . str_replace(" ", ",", $str) . "]", "MULTIPOLYGON");
			} else if (strpos($data, "MULTILINESTRING") !== false) {
				$str = "((" . explode("((", $data)[1];
				$str = str_replace("), (", "],[", $str);
				$str = str_replace(")", "]", $str);
				$str = str_replace("(", "[", $str);
				$str = str_replace(", ", "],[", $str);
				return $this->GeoObject("[" . str_replace(" ", ",", $str) . "]", "MULTILINESTRING");
			} else if (strpos($data, "MULTIPOINT") !== false) {
				$str = str_replace("MULTIPOINT ((", "", $data);
				$str = str_replace("))", "", $str);
				return $this->GeoObject("[[" . str_replace(" ", ",", $str) . "]]", "POINT");
			} else if (strpos($data, "POINT") !== false) {
				$str = str_replace("POINT (", "", $data);
				$str = str_replace(")", "", $str);
				return $this->GeoObject("[[" . str_replace(" ", ",", $str) . "]]", "POINT");
			}
		} catch (Exception $e) {
			Base::Debug($e);
			Base::Debug($data);
		}

		return false;
	}

	private function SaveCSV($name, $data)
	{
		//save the data to a timestamped .csv for archiving purposes 
		if (!file_exists($this->csvdir)) {
			mkdir($this->csvdir);
		}

		$filename = $this->csvdir . $name . ".csv";
		file_put_contents($filename, $data);

		return $filename;
	}

	private function GeoObject($str, $type)
	{
		$struct = new StdClass();
		$struct->json = $str;
		$struct->type = $type;
		return $struct;
	}
}
