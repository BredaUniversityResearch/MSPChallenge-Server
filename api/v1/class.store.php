<?php
	class Store extends Base {
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

			if($name == "raster") {
				return;
			}
			else{
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
			foreach($dirs as $workspace => $subdirs){
				foreach($subdirs as $datastore => $subdir){
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
			foreach($dirs as $workspace => $subdirs){
				$this->geoserver->request('workspaces/' . $workspace . "?recurse=true", "DELETE");
			}
		}

		public function DeleteWorkspace($workspace)
		{
			$this->geoserver->request('workspaces/' . $workspace . "?recurse=true", "DELETE");
		}

		public function GenerateWorkspaces($dirs)
		{
			foreach($dirs as $workspace => $subdirs){
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
			foreach($dirs as $key => $subdirs){
				foreach($subdirs as $subdir){
					$layers = $layer->GetLayers($key, $subdir);
					if($layers != null){
						foreach($layers as $l){
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
						if (filetype($dir."/".$object) == "dir") {
							self::DeleteDirectory($dir."/".$object); 
						}
						else {
							unlink($dir."/".$object);
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
			$storeFolder .= ($gameSessionId != GameSession::INVALID_SESSION_ID)? $gameSessionId."/" : "default/";
			return $storeFolder;	
		}

		public static function GetRasterArchiveFolder()
		{
			$folder = self::GetRasterStoreFolder();
			return $folder."archive/";
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
			
			// Harald, Jan 2021: the START TRANSACTION and COMMIT use below now needs to be reconsidered
			// should be ok, because in the new setup, the individual endpoint that is called, 
			// ... or the execution of a single batch of endpoints that is called, 
			// ... is wrapped in a transaction that can be rolled back in case of any kind of exception
			//Database::GetInstance()->query("START TRANSACTION");

			$filename = $layerMetaData['layer_name'];

			//raster layers are loaded differently
			if($layerMetaData['layer_geotype'] == "raster"){
				//Check if we should download the raster layer from geoserver. Default behaviour says we do, and just don't do it when it is specified.
				if (!array_key_exists("download_from_geoserver", $layerMetaData) || $layerMetaData["download_from_geoserver"] == true) {
					//download the image to the /raster folder from GeoServer
					$rasterMeta = $this->CreateRasterMeta($region, $filename);
					
					$url = $layer->GetExport($region, $filename, "PNG", $layerMetaData, $rasterMeta);

					self::EnsureFolderExists(self::GetRasterStoreFolder());
					file_put_contents(self::GetRasterStoreFolder() . $filename . ".png", $url);
				}
				else {
					//Create the metadata for the raster layer, but don't fill in the layer_raster field. This must be done by something else later.
					Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_geotype, layer_group, layer_editable) VALUES (?, ?, ?, ?)", array($filename, "raster", $region, 0));
				}
			}
			else{
				//Check if we should download the vector layer from geoserver. Default behaviour says we do, and just don't do it when it is specified.
				if (!array_key_exists("download_from_geoserver", $layerMetaData) || $layerMetaData["download_from_geoserver"] == true) {
					//download the geometry as a csv file from GeoServer
					$csv = $layer->GetExport($region, $filename, "CSV", "", null);
					$data = array_map("str_getcsv", preg_split('/\r*\n+|\r+/', $csv));
					$this->LoadCSV($data, true, $filename, $region);
				}
				else {
					//Create the metadata for the vector layer, but don't fill the geometry table. This will be up to the players.
					Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)", array($filename, $layerMetaData['layer_geotype'], $region));
				}
			}

			Database::GetInstance()->query("UPDATE geometry SET geometry_persistent=geometry_id WHERE geometry_persistent IS NULL");
			//Database::GetInstance()->query("COMMIT");

			$this->CheckForDuplicateMspIds();
		}

		private function CheckForDuplicateMspIds() 
		{
			$result = Database::GetInstance()->query("SELECT COUNT(*) as mspIdCount, geometry_mspid FROM geometry WHERE geometry_mspid IS NOT NULL GROUP BY geometry_mspid HAVING mspIdCount > 1");
			foreach($result as $duplicateId) {
				$duplicatedLayerNames = Database::GetInstance()->query("SELECT DISTINCT(layer.layer_name) FROM layer 
					INNER JOIN geometry ON geometry.geometry_layer_id = layer.layer_id
				WHERE geometry.geometry_mspid = ?", array($duplicateId['geometry_mspid']));
				$layerNames = implode(", ", array_map(function($data) { return $data['layer_name']; }, $duplicatedLayerNames));
				Base::Error("Found MSP ID ".$duplicateId['geometry_mspid']." which was duplicated ".$duplicateId['mspIdCount']." times in the following layers: ".$layerNames);
			}
		}

		private function CreateRasterMeta($workspace, $filename)
		{
			$meta = $this->geoserver->request("workspaces/" . $workspace . "/coveragestores/" . $filename . "/coverages/" . $filename . ".json");
			
			try{
				$data = json_decode($meta);

				if(!isset($data->coverage))
					throw new Exception("Layer could not be downloaded.");

				$bb = $data->coverage->nativeBoundingBox;

				$rasterMeta = array("url" => $filename . ".png", "boundingbox" => array(array($bb->minx, $bb->miny), array($bb->maxx, $bb->maxy)));

				$q = Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_raster, layer_geotype, layer_group, layer_editable) VALUES (?, ?, ?, ?, ?)", array($filename, json_encode($rasterMeta), "raster", $workspace, 0));

				return $rasterMeta;
			}
			catch(Exception $e){
				//if this triggers something went horribly wrong. Probably means that Geoserver is down or broken in some way
				Base::Debug("Something went wrong downloading " . $filename . ". Is the file on GeoServer? Should download be attempted at all?");
				//Base::Debug($e->getMessage()." - on line ".$e->getLine()." of file ".$e->getFile());
			}
		}

		private function LoadCSV($layerdata, $isstring = false, $filename="", $datastore="")
		{
			if(!$isstring) {
				$data = array_map('str_getcsv', file($this->csvdir . $filename . ".csv"));		
			}
			else{
				$data = $layerdata;
			}

			//store the index for the geometry data
			$geometry_id = 0;

			$headers = array();

			for($i = 0; $i < sizeof($data[0]); $i++) {
				if($data[0][$i] == "the_geom"){
					$geometry_id = $i;
				}
				array_push($headers, $data[0][$i]);
			}

			//set a geometry type to be saved in the database
			$type = "";
			if(strpos($data[1][$geometry_id], "MULTIPOLYGON") !== false) {
				$type = "polygon";
			}
			elseif(strpos($data[1][$geometry_id], "MULTILINESTRING") !== false) {
				$type = "line";
			}
			elseif(strpos($data[1][$geometry_id], "POINT") !== false) {
				$type = "point";
			}
			else {
				if(empty($data[0]) || $data[0][0] == "" || $data[0][0] == " " || empty($data[0][0])) {
					Base::Error("Could not find " . $filename . " on geoserver. Are you sure this file exists on filezilla & geoserver?");
				}
				else{
					Base::Error("Unknown geometry type found in " . $filename . ". Must be MULTIPOLYGON, MULTILINESTRING or POINT.");
				}

				return;
			}

			$layer_id = Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_geotype, layer_group) VALUES (?, ?, ?)", array($filename, $type, $datastore), true);

			//used for 
			$lastid = 0;
			$missingMspIds = 0;
			
			//start at 1 to skip the header row
			for($i = 1; $i < sizeof($data); $i++){
				if(count($data[$i]) <= 1) continue;

				try{
					$tmpjson = json_decode($data[$i][$geometry_id]);
				}
				catch(Exception $e){
					Base::Debug("EXCEPTION");
					//Base::Debug($i);
					//Base::Debug($data[$i]);
					Base::Debug($e);
					return;
				}
				
				if(json_last_error() === JSON_ERROR_NONE){
					$formatted = json_decode($data[$i][$geometry_id], true, 512, JSON_BIGINT_AS_STRING);
				}
				else{
					$geo = $this->ReformatGeoDataFromString($data[$i][$geometry_id]);
					if($geo === false) continue;
					$formatted = json_decode($geo->json, true, 512, JSON_BIGINT_AS_STRING);
				}

				$arr = array();
				for($j = 1; $j < sizeof($data[$i]); $j++){
					if($j != $geometry_id){
						$arr[$headers[$j]] = $data[$i][$j];
					}
				}

				if(!is_array($formatted))
					continue;

				if(isset($arr['type'])){
					$type = str_replace(' ', '', $arr['type']);
					unset($arr['type']);
					
					if($type == "")
						$type = '0';
				}
				else{
					$type = '0';
				}

				$mspid = null;

				if(isset($arr['mspid'])){
					$mspid = $arr['mspid'];
					unset($arr['mspid']);
				}
				else {
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

				try{
					switch($geo->type){
						case "MULTIPOLYGON":
							foreach($formatted as $multi){
								if(is_array($multi)){
									for($j = 0; $j < sizeof($multi); $j++){
										if(sizeof($multi) > 1 && $j != 0){
											//this is a subtractive polygon
											$this->InsertGeometry($data[$i][0], $layer_id, json_encode($multi[$j]), $jsondata, $countryId, $type, $mspid, $lastid);
										}
										else{
											$lastid = $this->InsertGeometry($data[$i][0], $layer_id, json_encode($multi[$j]), $jsondata, $countryId, $type, $mspid);
										}
									}
								}
							}
							break;
						case "POINT":
							$this->InsertGeometry($data[$i][0], $layer_id, json_encode($formatted), $jsondata, $countryId, $type, $mspid);
							break;
						case "MULTILINESTRING":
							foreach($formatted as $line){
								$this->InsertGeometry($data[$i][0], $layer_id, json_encode($line), $jsondata, $countryId, $type, $mspid);
							}
							break;
					}
				}
				catch(Exception $e){
					Base::Debug($e);
				}
			}

			if ($missingMspIds > 0) {
				Base::Warning("Encountered ".$missingMspIds." pieces of geometry that are missing MSP IDs in layer ".$filename.". Please re-export this layer to fix the MSP IDs");
			}
		}

		private function InsertGeometry($FID, $layerid, $geometry, $data, $countryId, $type, $mspid, $subtractive=null)
		{
			if($subtractive == null){
				$persistentid = Database::GetInstance()->query("INSERT INTO geometry (geometry_FID, geometry_layer_id, geometry_geometry, geometry_data, geometry_country_id, geometry_type, geometry_mspid) 
									VALUES (?, ?, ?, ?, ?, ?, ?)", 
									array($FID, $layerid, $geometry, $data, $countryId, $type, $mspid), true);
			}
			else{
				$persistentid = Database::GetInstance()->query("INSERT INTO geometry (geometry_FID, geometry_layer_id, geometry_geometry, geometry_data, geometry_country_id, geometry_type, geometry_subtractive) 
									VALUES (?, ?, ?, ?, ?, ?, ?)", 
									array($FID, $layerid, $geometry, $data, $countryId, $type, $subtractive), true);
			}

			return $persistentid;
		}

		public function ReformatGeoDataFromString($data)
		{
			try{
				if(strpos($data, "MULTIPOLYGON EMPTY") !== false){
					return false;
				}
				else if(strpos($data, "MULTIPOLYGON") !== false){
					$str = "(((" . explode("(((", $data)[1];
					$str = str_replace("), (", "]],[[", $str);
					$str = str_replace(")", "]", $str);
					$str = str_replace("(", "[", $str);
					$str = str_replace(", ", "],[", $str);
					return $this->GeoObject("[" . str_replace(" ", ",", $str) . "]", "MULTIPOLYGON");
				}
				else if(strpos($data, "MULTILINESTRING") !== false){
					$str = "((" . explode("((", $data)[1];
					$str = str_replace("), (", "],[", $str);
					$str = str_replace(")", "]", $str);
					$str = str_replace("(", "[", $str);
					$str = str_replace(", ", "],[", $str);
					return $this->GeoObject("[" . str_replace(" ", ",", $str) . "]", "MULTILINESTRING");
				}
				else if(strpos($data, "MULTIPOINT") !== false){
					$str = str_replace("MULTIPOINT ((", "", $data);
					$str = str_replace("))", "", $str);
					return $this->GeoObject("[[" . str_replace(" ", ",", $str) . "]]", "POINT");
				}
				else if(strpos($data, "POINT") !== false){
					$str = str_replace("POINT (", "", $data);
					$str = str_replace(")", "", $str);
					return $this->GeoObject("[[" . str_replace(" ", ",", $str) . "]]", "POINT");
				}				
			}
			catch(Exception $e){
				Base::Debug($e);
				Base::Debug($data);
			}

			return false;
		}

		private function SaveCSV($name, $data)
		{
			//save the data to a timestamped .csv for archiving purposes 
			if(!file_exists($this->csvdir)){
				mkdir($this->csvdir);
			}

			$filename = $this->csvdir . $name .".csv";
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
?>