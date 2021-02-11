<?php

	class Layer extends Base{
		public $geoserver;

		protected $allowed = array(
			"Delete", 
			"Export", 
			"Get",
			"GetRaster", 
			"GetRasterAtMonth",
			"ImportMeta", 
			"List",
			"Meta", 
			["MetaByName", Security::ACCESS_LEVEL_FLAG_NONE],  
			"Post", 
			"UpdateMeta",
			"UpdateRaster"
		);

		public function __construct($method = "")
		{
			parent::__construct($method);
			$this->geoserver = new GeoServer();
		}

		/**
		 * @apiGroup Layer
		 * @apiDescription **************************************
		 * @api {POST} /layer/Delete/
		 * @apiParam {int} layer_id Target layer id
		 * @apiSuccess {string} *********************************
		 */
		public function Delete(int $layer_id)
		{
			Database::GetInstance()->query("UPDATE layer SET layer_active=? WHERE layer_id=?", array(0, $layer_id));
		}
		
		/**
		 * @apiGroup Layer
		 * @apiDescription Export a layer to .json 
		 * @api {POST} /layer/Export/ Export
		 * @apiParam {int} layer_id id of the layer to export
		 * @apiSuccess {string} json formatted layer export with all geometry and their attributes
		 */
		public function Export(int $layer_id) 
		{
			$layer = Database::GetInstance()->query("SELECT * FROM layer WHERE layer_id=?", array($layer_id));
			if(empty($layer)){
				throw new Exception("Layer not found.");
			}
			$layer = $layer[0];

			$geometry = Database::GetInstance()->query("SELECT 
						geometry_id, geometry_FID, geometry_geometry, geometry_layer_id, geometry_type, geometry_data, geometry_mspid
				FROM layer 
				LEFT JOIN geometry ON geometry_layer_id=layer.layer_id 
				LEFT JOIN plan_layer ON plan_layer_layer_id=layer.layer_id
				LEFT JOIN plan ON plan_layer_plan_id=plan.plan_id
				WHERE geometry.geometry_active=? AND (layer_id=? OR layer_original_id=?) AND geometry_subtractive=? AND (plan_state=? OR plan_state=? OR plan_state IS NULL)", 
				array(1, $layer_id, $layer_id, 0, "APPROVED", "IMPLEMENTED")
			); // getting all active geometry, except those within plans that are not APPROVED or not IMPLEMENTED

			$subtractivearr = Database::GetInstance()->query("SELECT 
						geometry_id, geometry_FID, geometry_geometry, geometry_layer_id, geometry_type, geometry_data, geometry_subtractive 
				FROM geometry 
				WHERE geometry_layer_id=? AND geometry_subtractive<>? AND geometry_active=?", 
				array($layer_id, 0, 1)
			); // getting all active subtractive geometry for this layer, which only occurs in the original layer dataset because the client doesn't support adding/editing/deleting subtractive geometry ('holes')

			$all = array();
			
			// this part actually subtracts the latter geometry from the former geometry
			foreach($geometry as $shape){
				$g = array();

				$geom = $shape['geometry_geometry'];

				$g["FID"] = $shape["geometry_FID"];

				switch($layer['layer_geotype']){
					case "polygon":
						$g["the_geom"] = "MULTIPOLYGON (" . str_replace("[", "(", $geom);
						$g["the_geom"] = str_replace("]", ")", $g["the_geom"]) . ")";


						$g["the_geom"] = str_replace(",", " ", $g["the_geom"]);
						$g["the_geom"] = str_replace(") (", ", ", $g["the_geom"]);

						$g["the_geom"] = substr($g["the_geom"], 0, -2);

						$hassubs = false;
						$geostring = "";

						foreach($subtractivearr as $sub){
							if(isset($sub['geometry_subtractive']) && $sub['geometry_subtractive'] == $shape['geometry_id']){
								if(!$hassubs){
									$hassubs = true;
								}

								$geostring .= ",(";

								$geom = json_decode($sub['geometry_geometry'], true, 512, JSON_BIGINT_AS_STRING);
								foreach($geom as $geo){
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
					foreach($data as $key => $metadata){
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
		 * @api {POST} /layer/get/ Get
		 * @apiParam {int} layer_id id of the layer to return
		 * @apiSuccess {string} JSON JSON object
		 */
		public function Get(int $layer_id)
		{
			$vectorcheck = Database::GetInstance()->query("SELECT layer_geotype FROM layer WHERE layer_id = ?", array($layer_id));
			if (empty($vectorcheck[0]["layer_geotype"]) || $vectorcheck[0]["layer_geotype"] == "raster") {
				throw new Exception("Not a vector layer.");
			}
			
			$data = Database::GetInstance()->query("SELECT 
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
				WHERE layer.layer_id = ? ORDER BY geometry_FID ASC, geometry_subtractive ASC", array($layer_id));

			return Base::MergeGeometry($data);
		}

		/**
		 * @apiGroup Layer
		 * @api {POST} /layer/GetRaster GetRaster Retrieves image data for raster. 
		 * @apiParam layer_name Name of the layer corresponding to the image data.
		 * @apiDescription Returns the requested file as an dump
		 */
		public function GetRaster(int $month = -1, string $layer_name)
		{
			$layerData = Database::GetInstance()->query("SELECT layer_id, layer_raster FROM layer WHERE layer_name = ?", array($layer_name));
			if (count($layerData) != 1)
			{
				throw new Exception("Could not find layer with name " . $layer_name . " to request the raster image for");
			}

			$rasterData = json_decode($layerData[0]['layer_raster'], true);
			$rasterDataOriginal = $rasterData;
			$filePath = Store::GetRasterStoreFolder().$rasterData['url'];

			if ($month >= 0) 
			{
				$path_parts = pathinfo($rasterData['url']);
				$fileext = $path_parts['extension'];
				$filename = $path_parts['filename'];
				$rasterData['url'] = "archive/".$filename."_".$month.".".$fileext;
				$filePath = Store::GetRasterStoreFolder().$rasterData['url'];
				while (!file_exists($filePath) && $month > 0) {
					$month--;
					$rasterData['url'] = "archive/".$filename."_".$month.".".$fileext;
					$filePath = Store::GetRasterStoreFolder().$rasterData['url'];
				}
			}
			
			if (!file_exists($filePath))
			{
				// final try.... if $month = 0 and you couldn't find the file so far, just return the very original
				if ($month == 0) 
				{
					$filePath = Store::GetRasterStoreFolder().$rasterDataOriginal['url'];
					if (!file_exists($filePath)) throw new Exception("Could not find raster file for layer with name " . $layer_name . " at path " . $filePath);
				}
				else 
				{
					throw new Exception("Could not find raster file for layer with name " . $layer_name . " at path " . $filePath);
				}
			}
			$imageData = file_get_contents($filePath);

			$result['displayed_bounds'] = $rasterData['boundingbox'];
			$result['image_data'] = base64_encode($imageData);
			return $result; 
		}

		/**
		 * @apiGroup Layer
		 * @apiDescription **************************************
		 * @api {GET} /layer/************************************
		 * @apiParam {int} **************************************
		 * @apiSuccess {string} *********************************
		 */
		public function ImportMeta(string $configFilename, $geoserver_url, $geoserver_username, $geoserver_password)
		{
			$game = new Game();
			$geoserver = new GeoServer($geoserver_url, $geoserver_username, $geoserver_password);

			$struct = $geoserver->GetRemoteDirStruct();

			$data = $game->GetGameConfigValues($configFilename);
			$data = $data['meta'];

			foreach($data as $layerMetaData)
			{
				$dbLayerId = $this->VerifyLayerExists($layerMetaData["layer_name"], $struct);
				if ($dbLayerId != -1)
				{
					$this->ImportMetaForLayer($layerMetaData, $dbLayerId);
					$this->VerifyLayerTypesForLayer($layerMetaData, $dbLayerId);
				}
				else
				{
					Log::LogWarning("Could not find layer with name ".$layerMetaData["layer_name"]." in the database");
				}
			}
		}

		private function VerifyLayerTypesForLayer(array $layerData, int $layerId)
		{
			$alltypes = Database::GetInstance()->query("SELECT geometry_type FROM geometry WHERE geometry_layer_id=? GROUP BY geometry_type", array($layerId));
			$jsontype = $layerData['layer_type'];
			$errortypes = array();

			foreach($alltypes as $t){
				$typelist = explode(",", $t['geometry_type']);
				foreach($typelist as $singletype){
					//set a default type if one wasn't found
					if(!isset($jsontype[$singletype]) && !in_array($singletype, $errortypes) ) {
						Base::Error($layerData['layer_name'] . " Type " . $singletype . " was set in the geometry but was not found in the config file");
						array_push($errortypes, $singletype);

						//update the json array with the new type if it's not set, just to avoid errors on the client
						$jsontype[$singletype] = json_decode("{\"displayName\" : \"default\",\"displayPolygon\":true,\"polygonColor\":\"#6CFF1C80\",\"polygonPatternName\":5,\"displayLines\":true,\"lineColor\":\"#7AC943FF\",\"displayPoints\":false,\"pointColor\":\"#7AC943FF\",\"pointSize\":1.0}", true);
					}
				}
			}
		}

		private function VerifyLayerExists(string $layerName, array $struct): int
		{
			//check if the layer exists
			$d = Database::GetInstance()->query("SELECT layer_id FROM layer WHERE layer_name=?", array($layerName));

			if(empty($d))
			{
				Log::LogWarning($layerName ." was not found. Has this layer been renamed or removed?");
				$found = false;
				foreach($struct as $dir){
					foreach($dir as $subdir){
						foreach($subdir as $file){
							if($file == $layerName){
								Base::Error($layerName . " exists on geoserver but not in your database. Try recreating the database.");
								$found = true;
								break;
							}
						}
					}
				}
				if(!$found)
				{
					Base::Error($layerName . " has not been found on geoserver. Are you sure this file exists?");
				}
				return -1;
			}
			else 
			{
				return $d[0]["layer_id"];
			}
		}

		private function ImportMetaForLayer(array $layerData, int $dbLayerId)
		{
			$inserts = "";
			$insertarr = array();
			foreach($layerData as $key => $val){
				//these keys are to be ignored in the importer
				if($key == "layer_id" || 
					$key == "layer_name" || 
					$key == "layer_original_id" || 
					$key == "layer_raster" || 
					$key == "layer_width" || 
					$key == "layer_height" || 
					$key == "layer_raster_material" || 
					$key == "layer_raster_pattern" ||
					$key == "layer_raster_minimum_value_cutoff" ||
					$key == "layer_raster_color_interpolation" ||
					$key == "layer_raster_filter_mode" ||
					$key == "approval" || 
					$key == "layer_download_from_geoserver" ) {
					continue;
				}
				else{
					$inserts .= $key . "=?, ";
					if(is_array($val)){
						array_push($insertarr, json_encode($val));
					}
					else{
						if($val != null)
							array_push($insertarr, $val);
						else
							array_push($insertarr, "");
					}
				}
			}

			$inserts = substr($inserts, 0, -2);
			
			array_push($insertarr, $dbLayerId);
			Database::GetInstance()->query("UPDATE layer SET " . $inserts . " WHERE layer_id=?", $insertarr);

			//Import raster specific information. 
			if ($layerData["layer_geotype"] == "raster")
			{
				$sqlRasterInfo = Database::GetInstance()->query("SELECT layer_raster FROM layer WHERE layer_id=?", array($dbLayerId));
				$existingRasterInfo = json_decode($sqlRasterInfo[0]["layer_raster"], true);

				if (isset($layerData["layer_raster_material"])) {
					$existingRasterInfo["layer_raster_material"] = $layerData["layer_raster_material"];
				}
				if (isset($layerData["layer_raster_pattern"])) {
					$existingRasterInfo["layer_raster_pattern"] = $layerData["layer_raster_pattern"];
				}
				if (isset($layerData["layer_raster_minimum_value_cutoff"])) {
					$existingRasterInfo["layer_raster_minimum_value_cutoff"] = $layerData["layer_raster_minimum_value_cutoff"];
				}
				if (isset($layerData["layer_raster_color_interpolation"])) {
					$existingRasterInfo["layer_raster_color_interpolation"] = $layerData["layer_raster_color_interpolation"];
				}
				if (isset($layerData["layer_raster_filter_mode"])) {
					$existingRasterInfo["layer_raster_filter_mode"] = $layerData["layer_raster_filter_mode"];
				}
				
				Database::GetInstance()->query("UPDATE layer SET layer_raster = ? WHERE layer_id = ?", array(json_encode($existingRasterInfo), $dbLayerId));
			}

			Database::GetInstance()->query("UPDATE layer SET layer_type=? WHERE layer_id=?", array(json_encode($layerData['layer_type'], JSON_FORCE_OBJECT), $dbLayerId));
		}

		/**
		 * @apiGroup Layer
		 * @api {POST} /layer/List List Provides a list of raster layers and vector layers that have active geometry.
		 * @apiDescription Returns a json formatted array of layers, with layer_id, layer_name and layer_geotype objects defined per layer.
		 */
		public function List() 
		{
			$data = Database::GetInstance()->query("SELECT 
									layer.layer_id,
									layer.layer_name,
									layer.layer_geotype
								FROM layer 
								LEFT JOIN geometry ON layer.layer_id = geometry.geometry_layer_id
								WHERE layer.layer_name <> ''
								GROUP BY layer.layer_name");

			return $data;
		}

		/**
		 * @apiGroup Layer
		 * @apiDescription Get all the meta data of a single layer
		 * @api {POST} /layer/meta/ Meta
		 * @apiParam {int} layer_id layer id to return
		 * @apiSuccess {string} JSON JSON Object
		 */
		public function Meta($layer_id)
		{
			return $this->GetMetaForLayerById($layer_id); 
		}

		/**
		 * @apiGroup Layer
		 * @apiDescription Gets a single layer meta data by name. 
		 * @api {POST} /layer/MetaByName Meta By Name
		 * @apiParam {string} name name of the layer that we want the meta for
		 * @apiSuccess {string} JSON JSON Object. 
		 */
		public function MetaByName(string $name)
		{
			$result = array(); //"[]";
			$layerID = Database::GetInstance()->query("SELECT layer_id FROM layer where layer_name=?", array($name));
			if (count($layerID) > 0)
			{
				$result = $this->GetMetaForLayerById($layerID[0]["layer_id"])[0]; //Base::JSON($this->GetMetaForLayerById($layerID[0]["layer_id"])[0]);
			}
			else 
			{
				Base::Debug("Could not find layer with name ".$name);
			}
			return $result;
		}	

		/**
		 * @apiGroup Layer
		 * @apiDescription Create a new empty layer
		 * @api {POST} /layer/post/:id Post
		 * @apiParam {string} name name of the layer
		 * @apiParam {string} geotype geotype of the layer
		 * @apiSuccess {int} id id of the new layer
		 */
		public function Post(string $name, string $geotype)
		{
			$id = Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_geotype) VALUES (?, ?)", array($name, $geotype), true);
			return $id;
		}

		/**
		 * @apiGroup Layer
		 * @apiDescription Update the meta data of a layer
		 * @api {POST} /layer/UpdateMeta Update Meta
		 * @apiParam {string} short Update the display name of a layer
		 * @apiParam {string} category Update the category of a layer
		 * @apiParam {string} subcategory Update the subcategory of a layer
		 * @apiParam {string} type Update the type field of a layer
		 * @apiParam {int} depth Update the depth of a layer
		 * @apiParam {int} id id of the layer to update
		 * @apiSuccess {int} id id of the new layer
		 */
		public function UpdateMeta(string $short, string $category, string $subcategory, string $type, int $depth, int $id)
		{
			Database::GetInstance()->query("UPDATE layer SET layer_short=?, layer_category=?, layer_subcategory=?, layer_type=?, layer_depth=? WHERE layer_id=?", 
				array($short, $category, $subcategory, $type, $depth, $id));
		}

		/**
		* @apiGroup Layer
		* @api {POST} /layer/UpdateRaster UpdateRaster updates raster image
		* @apiParam {string} layer_name Name of the layer the raster image is for.
		* @apiParam {array} raster_bounds 2x2 array of doubles specifying [[min X, min Y], [max X, max Y]]
		* @apiParam {string} image_data Base64 encoded string of image data.
		* @apiDescription Returns nothing.
		*/
		public function UpdateRaster(string $layer_name, array $raster_bounds = null, string $image_data)
		{
			$layerData = Database::GetInstance()->query("SELECT layer_id, layer_raster FROM layer WHERE layer_name = ?", array($layer_name));
			if (count($layerData) != 1)
			{
				throw new Exception("Could not find layer with name " . $layer_name . " to update the raster image");
			}

			$rasterData = json_decode($layerData[0]['layer_raster'], true);
			$rasterDataUpdated = false;
			if (!empty($rasterData['url']))
			{
				if (file_exists(Store::GetRasterStoreFolder().$rasterData['url'])) 
				{
					$gameData = Database::GetInstance()->query("SELECT game_currentmonth FROM game")[0];
					Store::EnsureFolderExists(Store::GetRasterArchiveFolder());

					$layerPathInfo = pathinfo($rasterData['url']);
					$layerFileName = $layerPathInfo["filename"];
					$layerFileExt = $layerPathInfo["extension"];
					$newFileName = $layerFileName."_".$gameData['game_currentmonth'].".".$layerFileExt;

					file_put_contents(Store::GetRasterArchiveFolder().$newFileName, file_get_contents(Store::GetRasterStoreFolder().$rasterData['url']));
				}

				if ($raster_bounds !== null && !empty($raster_bounds))
				{
					$rasterData['boundingbox'] = $raster_bounds;
					$rasterDataUpdated = true;
				}

				$imageData = base64_decode($image_data);
				Store::EnsureFolderExists(Store::GetRasterStoreFolder());
				file_put_contents(Store::GetRasterStoreFolder().$rasterData['url'], $imageData);

				if ($rasterDataUpdated)
				{
					Database::GetInstance()->query("UPDATE layer SET layer_lastupdate = ?, layer_melupdate = 1, layer_raster = ? WHERE layer_id = ?", array(microtime(true), json_encode($rasterData), $layerData[0]['layer_id']));
				}
				else 
				{
					Database::GetInstance()->query("UPDATE layer SET layer_lastupdate = ?, layer_melupdate = 1 WHERE layer_id = ?", array(microtime(true), $layerData[0]['layer_id']));
				}
			}
			else
			{
				throw new Exception("Could not update raster for layer ".$layer_name." raster file name was not specified in raster metadata");
			}
		}

		// ----------------------------------
		// internal methods
		// ----------------------------------

		public function Latest($layers, $time, $planid)
		{
			//get all the geometry of a plan, excluding the geometry that has been deleted in the current plan, or has been replaced by a newer generation (so highest geometry_id of any persistent ID)
			foreach($layers as $key=>$layer){
				$layers[$key]['geometry'] = Database::GetInstance()->query("SELECT
						geometry_id as id, 
						geometry_geometry as geometry, 
						geometry_country_id as country,
						geometry_FID as FID,
						geometry_data as data,
						geometry_layer_id as layer,
						geometry_active as active,
						geometry_subtractive as subtractive,
						geometry_type as type,
						geometry_persistent as persistent,
						geometry_mspid as mspid
					FROM geometry
					WHERE geometry_layer_id=:layer_id AND geometry_deleted = 0 
						AND geometry_id NOT IN (SELECT plan_delete.plan_delete_geometry_persistent FROM plan_delete WHERE plan_delete.plan_delete_geometry_persistent = geometry_id AND plan_delete.plan_delete_layer_id = :layer_id)
						AND (geometry_id, geometry_persistent) IN (SELECT MAX(geometry_id), geometry_persistent FROM geometry WHERE geometry_layer_id = :layer_id GROUP BY geometry_persistent) 	
					ORDER BY geometry_FID ASC, geometry_subtractive ASC", 
				array("layer_id" => $layer['layerid']));
				$layers[$key]['geometry'] = Base::MergeGeometry($layers[$key]['geometry'], false);
			}

			$deleted = Database::GetInstance()->query("SELECT plan_delete_geometry_persistent as geometry, plan_delete_layer_id as layerid, layer_original_id as original
				FROM plan_delete
				LEFT JOIN layer ON plan_delete.plan_delete_layer_id=layer.layer_id
				WHERE plan_delete_plan_id=? ORDER BY layerid ASC", array($planid));

			foreach($deleted as $del){
				$found = false;

				foreach($layers as &$layer){
					if(isset($layer['layerid']) && $del['layerid'] == $layer['layerid']){
						if(!isset($layer['deleted'])){
							$layer['deleted'] = array();
						}
						array_push($layer['deleted'], $del['geometry']);
						$found = true;
						break;
					}
				}

				if(!$found){
					array_push($layers, array());

					$layers[sizeof($layers)-1]['deleted'] = array(
						'layerid' => $del['layerid'],
						'original' => $del['original'],
						'deleted' => array($del['geometry'])
					);
				}
			}
			
			return $layers;
		}

		public function LatestRaster($time)
		{
			return Database::GetInstance()->query("SELECT layer_raster as raster, layer_id as id FROM layer WHERE layer_geotype=? AND layer_lastupdate>?", array("raster", $time));
		}

		public function GetExport($workspace, $layer, $format = "GML", $layerdata="", $rasterMeta = null)
		{
			//this downloads the data from GeoServer through their REST API
			$maxGMLfeatures = 1000000;
			$layer = str_replace(" ", "%20", $layer);
			
			if($format == "GML"){
				return $this->geoserver->ows($workspace . "/ows?service=WFS&version=1.0.0&request=GetFeature&typeName=" . urlencode($workspace) . ":" . urlencode($layer) . "&maxFeatures=" . $maxGMLfeatures);
			}
			else if($format == "CSV"){
				$response = $this->geoserver->ows($workspace . "/ows?service=WFS&version=1.0.0&outputFormat=csv&request=GetFeature&typeName=" . urlencode($workspace) . ":" . urlencode($layer) . "&maxFeatures=" . $maxGMLfeatures);
				return $response;
			}
			else if ($format == "JSON") {
				$response = $this->geoserver->ows($workspace . "/ows?service=WFS&version=1.0.0&outputFormat=json&request=GetFeature&typeName=" . urlencode($workspace) . ":" . urlencode($layer) . "&maxFeatures=" . $maxGMLfeatures);
				return $response;
			}
			else if($format == "PNG"){
				if ($rasterMeta === null)
				{
					throw new Exception("Tried to export ".$layer." from geoserver in format ".$format." but rasterMeta was not specified");
				}
				$deltaSizeX = $rasterMeta["boundingbox"][1][0] - $rasterMeta["boundingbox"][0][0];
				$deltaSizeY = $rasterMeta["boundingbox"][1][1] - $rasterMeta["boundingbox"][0][1];
				$widthRatioMultiplier = $deltaSizeX / $deltaSizeY;
								
				if(isset($layerdata['layer_height'])){
					$height = $layerdata['layer_height'];
				}
				
				$width = $height * $widthRatioMultiplier;
				$bounds = $rasterMeta["boundingbox"][0][0].",".$rasterMeta["boundingbox"][0][1].",".$rasterMeta["boundingbox"][1][0].",".$rasterMeta["boundingbox"][1][1];
				return $this->geoserver->ows($workspace . "/wms/reflect?layers=" . urlencode($workspace) . ":" . urlencode($layer) . "&format=image/png&transparent=FALSE&width=" . round($width) . "&height=" . $height ."&bbox=".$bounds);
			}
			else{
				throw new Exception("Incorrect format, use GML, CSV, JSON or PNG");
			}
		}

		public function GetLayers($workspace, $datastore)
		{
			$data = json_decode($this->geoserver->request('workspaces/' . urlencode($workspace) . '/datastores/' . urlencode($datastore) . '/featuretypes.json'));

			if(property_exists($data->featureTypes, "featureType"))
				return $data->featureTypes->featureType;

			return null;
		}

		public function ReturnRasterById($layer_id=0)
		{
			if (empty($layer_id)) throw new Exception("Empty layer_id.");

			$layerData = Database::GetInstance()->query("SELECT layer_id, layer_raster FROM layer WHERE layer_id = ?", array($layer_id));
			if (count($layerData) != 1) throw new Exception("Could not find layer with id " . $layer_id . " to request the raster image for");

			$rasterData = json_decode($layerData[0]['layer_raster'], true);
			$filePath = Store::GetRasterStoreFolder().$rasterData['url'];
			if (!file_exists($filePath))
			{
				throw new Exception("Could not find raster file at path " . $filePath);
			}
			return file_get_contents($filePath);
		}

		private function GetMetaForLayerById($layerId)
		{
			$data = Database::GetInstance()->query("SELECT * FROM layer WHERE layer_id=?", array($layerId));
			Layer::FixupLayerMetaData($data[0]);
			return $data;
		}

		public static function FixupLayerMetaData(&$data)
		{
			$data['layer_type'] = json_decode($data['layer_type'], true);
			$data['layer_info_properties'] = (isset($data['layer_info_properties']))? json_decode($data['layer_info_properties']) : null;
			$data['layer_text_info'] = (isset($data['layer_text_info']))? json_decode($data['layer_text_info']) : null;
		}

	}

?>