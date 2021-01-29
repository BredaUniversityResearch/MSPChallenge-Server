<?php

class SEL extends Base
{
	protected $allowed = array(
		"GetAreaOutputConfiguration", 
		"GetConfiguredRouteIntensities",
		"GetCountryBorderGeometry",
		"GetRestrictionGeometry",
		"GetShippingLaneGeometry", 
		"GetShippingPortGeometry",
		"GetPortIntensities", 
		"GetSELConfig",
		"GetShipTypes",
		"GetShipRestrictionGroups", 
		"GetShipRestrictionGroupExceptions",
		"GetHeatmapOutputSettings",
		"GetHeatmapSettings",
		"SetRastersUpdated",
		"SetShippingIntensityValues",
		"StartExe",
		"GetKPIDefinition",
		"NotifyUpdateFinished",
		"GetUpdatePackage",
		"GetSELGameClientConfig"
	);

	public function __construct($method = "")
	{
		parent::__construct($method);
	}

	//SEL Input Queries
	/**
	* @apiGroup SEL
	* @api {GET} /sel/GetPlayableAreaGeometry GetPlayableAreaGeometry
	* @apiDescription Gets the geometry associated with the playable area layer
	*/
	public function GetAreaOutputConfiguration() 
	{
		$game = new Game();
		$config = $game->GetGameConfigValues();

		$result = array("simulation_area" => $this->CalculateAlignedSimulationBounds($config));

		if (isset($config["MEL"]))
		{
			$melConfig = &$config["MEL"];
			$result["mel_area"]["x_min"] = $melConfig["x_min"];
			$result["mel_area"]["x_max"] = $melConfig["x_max"];
			$result["mel_area"]["y_min"] = $melConfig["y_min"];
			$result["mel_area"]["y_max"] = $melConfig["y_max"];
			$result["mel_cell_size"] = $melConfig["cellsize"];
			$result["mel_resolution_x"] = $melConfig["columns"];
			$result["mel_resolution_y"] = $melConfig["rows"];
		}

		if (isset($config["SEL"]["output_configuration"])) 
		{
			$result = array_merge($result, $config["SEL"]["output_configuration"]);
		}
		
		return $result; //Base::JSON($result);
	}

	//Returns the aligned simulation bounds as defined by the playarea. Bounds are aligned to the MEL area so the pixels in the raster should line up.
	private function CalculateAlignedSimulationBounds($config = null)
	{
		if ($config == null)
		{
			$game = new Game();
			$config = $game->GetGameConfigValues();
		}

		$data = Database::GetInstance()->query("SELECT geometry.geometry_geometry as geometry FROM geometry
			LEFT JOIN layer ON geometry.geometry_layer_id = layer.layer_id 
			WHERE layer.layer_name LIKE \"_PLAYAREA%\"");
		$bounds = $this->CalculateBoundsFromGeometry(json_decode($data[0]["geometry"]));

		if (isset($config["MEL"]))
		{
			$melConfig = &$config["MEL"];
			$xOffset = $melConfig["x_min"] - $bounds["x_min"];
			$yOffset = $melConfig["y_min"] - $bounds["y_min"];

			$melCellSize = $melConfig["cellsize"];
			$simulationAreaShift = array(fmod($xOffset, $melCellSize), fmod($yOffset, $melCellSize));

			$xSize = $this->CeilToCellSize($bounds["x_max"] - $bounds["x_min"], $melCellSize);
			$ySize = $this->CeilToCellSize($bounds["y_max"] - $bounds["y_min"], $melCellSize);

			$bounds["x_min"] += $simulationAreaShift[0];
			$bounds["y_min"] += $simulationAreaShift[1];
			$bounds["x_max"] = $bounds["x_min"] + $xSize;
			$bounds["y_max"] = $bounds["y_min"] + $ySize;
		}
		return $bounds;
	}

	private function CeilToCellSize($input, $cellSize)
	{
		return ceil($input / $cellSize) * $cellSize;
	}

	private function CalculateBoundsFromGeometry($geometryData)
	{
		$result = array("x_min" => 1e25, "y_min" => 1e25, "x_max" => -1e25, "y_max" => -1e25);
		foreach($geometryData as $g)
		{
			if ($g[0] < $result["x_min"]) 
			{
				$result["x_min"] = $g[0];
			}
			if ($g[1] < $result["y_min"])
			{
				$result["y_min"] = $g[1];
			}
			if ($g[0] > $result["x_max"])
			{
				$result["x_max"] = $g[0];
			}
			if ($g[1] > $result["y_max"])
			{
				$result["y_max"] = $g[1];
			}
		}
		return $result;
	}

	/**
	* @apiGroup SEL
	* @api {GET} /sel/GetConfiguredRouteIntensities GetConfiguredRouteIntensities
	* @apiDescription Returns the configured routes setup in the config file as an JSON encoded array. 
	*/
	public function GetConfiguredRouteIntensities()
	{
		$configData = $this->GetSELConfigInternal();
		return $configData["configured_routes"];
	}

	/**
	* @apiGroup SEL
	* @api {GET} /sel/GetCountryBorderGeometry GetCountryBorderGeometry
	* @apiDescription Returns all geometry which defines the areas of each country. For instance in the NorthSea this will be the EEZ layer
	*/
	public function GetCountryBorderGeometry()
	{
		$loadedConfig = $this->GetSELConfigInternal();
		$encodedLayerTypes = Database::GetInstance()->query("SELECT layer.layer_type FROM layer WHERE layer.layer_name = ?", array($loadedConfig["country_border_layer"]));
		$layerTypes = json_decode($encodedLayerTypes[0]["layer_type"], true);

		$data = Database::GetInstance()->query("SELECT geometry.geometry_geometry as geometry, geometry.geometry_data, geometry.geometry_type
		FROM geometry
			LEFT JOIN layer ON geometry.geometry_layer_id = layer.layer_id 
		WHERE layer.layer_name = ?", array($loadedConfig["country_border_layer"]));

		foreach($data as $key => $value)
		{
			$data[$key]["geometry"] = json_decode($value["geometry"]);
			
			//Owning country is encoded in the layer type's value field.
			$geometryType = $data[$key]["geometry_type"];
			$countryId =  $layerTypes[$geometryType]["value"];
			$data[$key]["owning_country_id"] = $countryId;
		}
		return $data;
	}

	private function ImplodeForMysqlInStatement($array)
	{
		$result = "";
		foreach($array as $val)
		{
			if (!empty($result))
			{
				$result = $result.",";
			}
			$result = $result.Database::GetInstance()->quote($val);
		}
		return $result;
	}

	/**
	* @apiGroup SEL
	* @api {GET} /sel/GetRestrictionGeometry GetRestrictionGeometry
	* @apiDescription Gets all of the restriction geometry that the ships aren't allowed to cross
	*/
	public function GetRestrictionGeometry()
	{
		$shippingLayers = $this->GetSELConfigInternal()["shipping_lane_layers"];
		
		//First gather all the layer ids that our layers intersect with.
		$restrictionLayerIds = array();
		foreach($shippingLayers as $shippingLayer)
		{
			$restrictionLayerIds = array_unique(array_merge($restrictionLayerIds, $this->GetRestrictionLayersForLayer($shippingLayer)));
		}

		//Gather all the data for layers and plan layers that intersect with the shipping layers via restrictions. 
		$result = array();		
		foreach($restrictionLayerIds as $restrictionLayerId)
		{
			$restrictionData = Database::GetInstance()->query("SELECT geometry.geometry_id, layer.layer_id, layer.layer_geotype, layer.layer_original_id, geometry.geometry_geometry as geometry, geometry.geometry_type
					FROM geometry
						LEFT JOIN layer ON geometry.geometry_layer_id = layer.layer_id 
						LEFT JOIN plan_layer ON layer.layer_id = plan_layer.plan_layer_layer_id
						LEFT JOIN plan ON plan_layer.plan_layer_plan_id = plan.plan_id
					WHERE (layer.layer_id = ? OR (layer.layer_original_id = ? AND layer.layer_original_id IS NOT null)) AND (plan.plan_state = \"IMPLEMENTED\" OR plan.plan_state IS NULL) AND geometry.geometry_active = 1",
				array($restrictionLayerId, $restrictionLayerId));
			
			foreach($restrictionData as $d)
			{
				$layerId = $d["layer_original_id"];
				if ($layerId != null)
				{
					$originalLayerData = Database::GetInstance()->query("SELECT layer.layer_geotype FROM layer WHERE layer.layer_id = ?", array($layerId));
					$d["layer_geotype"] = $originalLayerData[0]["layer_geotype"];
				}
				else
				{		
					$layerId = $d["layer_id"];
				}

				//Add in the layer types
				$layerType = explode(",", $d['geometry_type']);
				//Process Geometry. If the geotype is polygon push the last entry of the geometry onto the end of the list so it closes the polygon nicely, otherwise we will leave a gap.
				$geometryData = json_decode($d["geometry"]);
				if (strtolower($d["layer_geotype"]) == "polygon") 
				{
					$geometryData[] = $geometryData[0];
				}

				//And transform the data so SEL knows what to do with it )) 
				$transformedData = array("geometry_id" => $d["geometry_id"], "geometry" => $geometryData, "layer_geotype" => $d["layer_geotype"], "layer_id" => $layerId, "layer_types" => $layerType);
				array_push($result, $transformedData);
			}
		}

		return $result;
	}

	//Gets all the referenced layer ids by the restriction system for a particular layer with layerName.
	private function GetRestrictionLayersForLayer($layerName)
	{
		$result = array();
		$layerId = Database::GetInstance()->query("SELECT layer.layer_id FROM layer WHERE layer_name = ?", array($layerName));
		if (!empty($layerId))
		{
			$sourceLayerId = $layerId[0]["layer_id"];
			$targetLayers = Database::GetInstance()->query("SELECT DISTINCT(layer_id) 
				FROM layer 
					INNER JOIN restriction ON restriction.restriction_start_layer_id = layer.layer_id OR restriction.restriction_end_layer_id = layer.layer_id
				WHERE layer.layer_id != ? AND (restriction.restriction_start_layer_id = ? OR restriction.restriction_end_layer_id = ?)", 
				array($sourceLayerId, $sourceLayerId, $sourceLayerId));
			foreach($targetLayers as $targetLayerId) 
			{
				$result[] = $targetLayerId['layer_id'];
			}
		}
		else 
		{
			throw new Exception("Could not find layer with name \"".$layerName."\"");
		}
		return $result;
	}

	/**
	* @apiGroup SEL
	* @api {POST} /sel/GetShippingLaneGeometry GetShippingLaneGeometry
	* @apiDescription Returns all the geometry associated with shipping lanes. 
	*/
	public function GetShippingLaneGeometry()
	{
		$shippingLayers = $this->GetSELConfigInternal()["shipping_lane_layers"];
		$shipTypeMappings = $this->GetSELConfigInternal()["layer_type_ship_type_mapping"];

		$layerIds = Database::GetInstance()->query("SELECT layer_id, layer_type FROM layer WHERE layer_name IN (".$this->ImplodeForMysqlInStatement($shippingLayers).")");
		
		$result = array();

		foreach($layerIds as $layerId)
		{
			$sourceLayerType = json_decode($layerId["layer_type"], true);
			
			$data = Database::GetInstance()->query("SELECT geometry.geometry_id, geometry.geometry_geometry, geometry.geometry_type, geometry.geometry_data FROM geometry
					LEFT JOIN layer ON geometry.geometry_layer_id = layer.layer_id 
					LEFT JOIN plan_layer ON layer.layer_id = plan_layer.plan_layer_layer_id
					LEFT JOIN plan ON plan_layer.plan_layer_plan_id = plan.plan_id
				WHERE geometry.geometry_active = 1 AND (plan.plan_state = \"IMPLEMENTED\" OR plan.plan_state IS NULL) AND (layer.layer_id = ? OR layer.layer_original_id = ?)", array($layerId["layer_id"], $layerId["layer_id"]));
			
			foreach($data as $d)
			{
				$mappedShipTypes = null;
				foreach(explode(",", $d["geometry_type"]) as $layerTypeId)
				{
					$entityTypeName = $sourceLayerType[$layerTypeId]["displayName"]; 
					$entityTypeShipGroups = $this->FindShipTypesForLayerType($shipTypeMappings, $entityTypeName);
					if ($entityTypeShipGroups != null)
					{
						if ($mappedShipTypes == null)
						{
							$mappedShipTypes = $entityTypeShipGroups;
						}
						else 
						{
							$mappedShipTypes = array_merge($mappedShipTypes, $entityTypeShipGroups);
						}
					}
				}

				$transformedData = array("geometry_id" => $d["geometry_id"], "geometry" => json_decode($d["geometry_geometry"]), "ship_type_ids" => $mappedShipTypes, "geometry_data" => json_decode($d["geometry_data"]));
				array_push($result, $transformedData);
			}    
		}

		return $result;
	}

	//Finds the EntityType $typeName in the restriction group mappings $groupMappings. Returns null if nothing is found.
	private function FindShipTypesForLayerType($groupMappings, $typeName)
	{
		$shipTypes = null;
		foreach($groupMappings as $mapping) {
			if ($mapping["layer_type"] == $typeName) {
				$shipTypes = $mapping["ship_type_ids"];
				break;
			}
		}
		return $shipTypes;
	}

	/**
	* @apiGroup SEL
	* @api {POST} /sel/GetShippingPortGeometry GetShippingPortGeometry
	* @apiDescription Returns all geometry associated with shipping ports for the current game. Returns the values in a comma separated list
	*/
	public function GetShippingPortGeometry()
	{
		$acceptable_states = array("ASSEMBLY", "ACTIVE", "DISMANTLE");
		$portLayers = $this->GetSELConfigInternal()["port_layers"];	
		$result = array();

		foreach($portLayers as $portLayer)
		{
			//As soon as we have plans that can specify ports this query needs to change to include those plans.
			$layerId = Database::GetInstance()->query("SELECT layer_id, layer_states FROM layer
			WHERE layer.layer_name = ?", array($portLayer['layer_name']))[0];
		
			$data = Database::GetInstance()->query("SELECT geometry.geometry_id, geometry.geometry_persistent, geometry.geometry_geometry, geometry.geometry_data, geometry.geometry_mspid, plan.plan_gametime FROM geometry
				LEFT JOIN layer ON geometry.geometry_layer_id = layer.layer_id 
				LEFT JOIN plan_layer ON layer.layer_id = plan_layer.plan_layer_layer_id
				LEFT JOIN plan ON plan_layer.plan_layer_plan_id = plan.plan_id
			WHERE (layer.layer_id = ? OR layer.layer_original_id = ?) AND (plan.plan_state = \"IMPLEMENTED\" OR plan.plan_state = \"APPROVED\" OR plan.plan_state IS NULL) AND geometry.geometry_active = 1", 
			array($layerId['layer_id'], $layerId['layer_id']));

			$layerStateData = json_decode($layerId['layer_states'], true);
			$constructionTime = 0;

			foreach($layerStateData as $layerState)
			{
				if (empty($layerState["state"]) || !in_array($layerState["state"], $acceptable_states)) 
				{
					throw new Exception("Layer State of ".$portLayer['layer_name']." is not correctly defined in the Meta part of the config file, please fix this.");
				}
				if ($layerState["state"] == "ASSEMBLY") 
				{
					$constructionTime = $layerState["time"];
				}
			}

			foreach($data as $d)
			{
				$portId = "GENERATED_PORT_NAME_".$d["geometry_id"];
				if (!empty($d["geometry_data"]))
				{
					$portData = array_change_key_case(json_decode($d["geometry_data"], true), CASE_LOWER);
					if (array_key_exists("name", $portData))
					{
						$portId = $portData["name"];
					}
				}
				
				//Ensure that base layer plans are always impelemented and that we never send NULL back.
				if ($d['plan_gametime'] == null) 
				{
					$d['plan_gametime'] = -100;
				}

				$transformedData = array("geometry_id" => $d["geometry_id"], 
					"port_id" => $portId, 
					"geometry" => json_decode($d["geometry_geometry"]), 
					"geometry_persistent_id" => $d["geometry_persistent"],
					"port_type" => $portLayer['port_type'], 
					"construction_start_time" => $d['plan_gametime'] - $constructionTime,
					"construction_end_time" => $d['plan_gametime']);
				array_push($result, $transformedData);
			}
		}

		return $result;
	}

	/**
	* @apiGroup SEL
	* @api {GET} /sel/GetPortIntensities GetPortIntensities
	* @apiDescription Returns all the configured intensities for the shipping ports indexed by port geometry id.
	*/
	public function GetPortIntensities()
	{
		$portIntensity = $this->GetSELConfigInternal()["port_intensity"];
		return $portIntensity;
	}

	private function GetSELConfigInternal()
	{
		$game = new Game();
		$tmp = $game->GetGameConfigValues();
		if (array_key_exists("SEL", $tmp))
		{
			return $tmp["SEL"];
		}
		else 
		{
			return null;
		}
	}

	/**
	* @apiGroup SEL
	* @api {GET} /sel/GetSELConfig GetSELConfig
	* @apiDescription Returns a collection of region-specific SEL config values.
	*/
	public function GetSELConfig()
	{
		$game = new Game();
		$gameConfig = $game->GetGameConfigValues();
		$selConfig = $gameConfig["SEL"];
		$result["shipping_lane_point_merge_distance"] = $selConfig["shipping_lane_point_merge_distance"];
		$result["shipping_lane_subdivide_distance"] = $selConfig["shipping_lane_subdivide_distance"];
		$result["shipping_lane_implicit_distance_limit"] = $selConfig["shipping_lane_implicit_distance_limit"];
		if (isset($selConfig["maintenance_destinations"]))
		{
			$result["maintenance_destinations"] = $selConfig["maintenance_destinations"];
		}
		$result["restriction_point_size"] = $gameConfig["restriction_point_size"];

		return $result;
	}

	/**
	* @apiGroup SEL
	* @api {GET} /sel/GetShipTypes GetShipTypes
	* @apiDescription Returns all configured ship types for the current session
	*/
	public function GetShipTypes()
	{
		$configData = $this->GetSELConfigInternal();
		return $configData["ship_types"];
	}

	/**
	* @apiGroup SEL
	* @api {GET} /sel/GetShipRestrictionGroupExceptions GetShipRestrictionGroupExceptions
	* @apiDescription Returns all restriction group exceptions configured in the configuration file. Returns the data in the format of { "layer_id": [int layerId], "layer_type": [string layerType], "allowed_restriction_groups": [int[] shipRestrictionGroups] }
	*/
	public function GetShipRestrictionGroupExceptions()
	{
		$configData = $this->GetSELConfigInternal();
		$result = array();
		foreach($configData["restriction_layer_exceptions"] as $layerExceptionEntry)
		{
			$layerIdData = Database::GetInstance()->query("SELECT layer_id, layer_type FROM layer WHERE layer_name = ?", array($layerExceptionEntry["layer_name"]));
			if(!empty($layerIdData))
			{
				$allowedShipIds = [];
				$multipliers = [];
				foreach($layerExceptionEntry["allowed_ships"] as $allowedShipData) 
				{
					$costMultiplier = isset($allowedShipData["cost_multiplier"])? $allowedShipData["cost_multiplier"]: 1.0;
					foreach($allowedShipData["ship_type_ids"] as $shipTypeId)
					{
						$allowedShipIds[] = $shipTypeId;
						$multipliers[] = $costMultiplier;
					}
				}

				if (isset($layerExceptionEntry["layer_types"]) && count($layerExceptionEntry["layer_types"]) > 0) //If the layer_types is not defined or types are empty assume all types (-1)
				{
					$layerTypesToMatch = $layerExceptionEntry["layer_types"];
					$layerTypeData = json_decode($layerIdData[0]["layer_type"], true);
					foreach($layerTypeData as $key => $layerType) 
					{
						$arrayIndex = array_search($layerType["displayName"], $layerTypesToMatch);
						if ($arrayIndex !== false)
						{
							array_push($result, array(
								"layer_id" => $layerIdData[0]["layer_id"], 
								"layer_type_id" => $key, 
								"allowed_ship_type_ids" => $allowedShipIds,
								"cost_multipliers" => $multipliers
							));
							array_splice($layerTypesToMatch, $arrayIndex, 1);
						}
					}

					if (count($layerTypesToMatch) > 0)
					{
						$layerTypes = array();
						foreach($layerTypeData as $layerType)
						{
							$layerTypes[] = $layerType["displayName"];
						}
						Log::ServerEvent("SEL_API", Log::Warning, "Could not find layer type(s) ".implode(",", $layerTypesToMatch)." in layer ".$layerExceptionEntry["layer_name"]." available types: ".implode(",", $layerTypes));
					}
				} 
			}
			else 
			{
				Log::ServerEvent("SEL_API", Log::Error, "Malformed config file. Could not find layer id for layer with name \"".$layerExceptionEntry["layer_name"]."\" for exception");
			}
		}
		return $result;
	}

	/** 
	* @apiGroup SEL
	* @api {GET} /sel/GetHeatmapOutputSettings GetHeatmapOutputSettings
	* @apiDescription Gets heatmap settings as defined in the config file. These settings include the output size, internal layer name, 
	* raster output location, raster bounds and intensity mappings.
	*/
	public function GetHeatmapOutputSettings()
	{
		$configData = $this->GetSELConfigInternal()["heatmap_settings"];
		foreach($configData as $key => $value)
		{
			$rasterData = Database::GetInstance()->query("SELECT layer_raster FROM layer WHERE layer_name = ?", array($value["layer_name"]));
			if (count($rasterData) > 0 && $rasterData[0]["layer_raster"] != null)
			{
				$decodedRasterData = json_decode($rasterData[0]["layer_raster"]);
				if (isset($decodedRasterData->boundingbox))
				{
					$configData[$key]["raster_bounds"] = $decodedRasterData->boundingbox;
				}
			}
		}
		return $configData;
	}

	/** 
	* @apiGroup SEL
	* @api {GET} /sel/GetHeatmapSettings GetHeatmapSettings
	* @apiDescription Gets the persistent riskmap settings. 
	*/
	public function GetHeatmapSettings()
	{
		$configValues = $this->GetSELConfigInternal();

		$riskmapSettings = $configValues["risk_heatmap_settings"];

		$restrictionLayerExceptions = array();
		foreach($riskmapSettings["restriction_layer_exceptions"] as $data)
		{
			$layerData = Database::GetInstance()->query("SELECT layer_id FROM layer WHERE layer_name = ?", array($data));
			if (count($layerData) > 0)
			{ 
				array_push($restrictionLayerExceptions, $layerData[0]["layer_id"]);
			}
			else 
			{
				Log::ServerEvent("SEL_API", Log::Warning, "Unknown layer with name ".$data." found in SEL Configuration file");
			}
		}
		$riskmapSettings["restriction_layer_exceptions"] = $restrictionLayerExceptions;
		
		$bleedConfig = $configValues["heatmap_bleed_config"];

		$result = array("riskmap_settings" => $riskmapSettings, "bleed_config" => $bleedConfig);
		return $result;
	}

	/**
	* @apiGroup SEL
	* @api {POST} /sel/SetRastersUpdated/:layer_names SetRastersUpdated
	* @apiParam {string[]} layer_names json-encoded array of layer names that have been updated (e.g. ["Layer1", "Layer2", "Layer3"]
	* @apiDescription Notifies the running game that all the configured rasters have been updated to a more recent version so the game will reload them.
	*/
	public function SetRastersUpdated(string $layer_names)
	{
		if (empty($layer_names))
		{
			throw new Exception("layer_names was empty.");
		}
		$layerNames = json_decode($layer_names);
		foreach($layerNames as $layerName)
		{
			Database::GetInstance()->query("UPDATE layer SET layer_lastupdate = ?, layer_melupdate = 1 WHERE layer_name = ?", array(microtime(true), $layerName));
		}
	} 

	/**
	* @apiGroup SEL
	* @api {POST} /sel/SetShippingIntensityValues SetShippingIntensityValues
	* @apiParam {string} values Json encoded string of an <int, int> key value pair where keys define the geometry ID and the values define the shipping intensity.
	* @apiDescription Sets the "Shipping_Intensity" data field to the supplied value for all submitted IDs
	*/
	public function SetShippingIntensityValues(string $values)
	{
		if (empty($values))
		{
			throw new InvalidArgumentException("values field was empty.");
		}

		$decodedValues = json_decode($values);

		foreach($decodedValues as $geometryId => $intensityValue)
		{
			$queryResult = Database::GetInstance()->query("SELECT geometry_data FROM geometry WHERE geometry_id = ?", array($geometryId));
			$baseGeometryData = json_decode($queryResult[0]["geometry_data"], true);
			$baseGeometryData["Shipping_Intensity"] = $intensityValue;
			$newGeometryData = Base::JSON($baseGeometryData);
			Database::GetInstance()->query("UPDATE geometry SET geometry_data = ? WHERE geometry_id = ?", array($newGeometryData, $geometryId)); 
		}
 
	}

	/**
	 * @apiGroup SEL
	 * @api {GET} /sel/ReimportShippingLayers ReimportShippingLayers
	 * @apiDescription Creates the raster layers required for shipping. 
	 */
	public function ReimportShippingLayers()
	{
		$defaultEntityTypes = "{
			\"0\": {
				\"displayName\": \"default\",
				\"displayPolygon\": true,
				\"polygonColor\": \"#6CFF1C80\",
				\"polygonPatternName\": 5,
				\"displayLines\": true,
				\"lineColor\": \"#7AC943FF\",
				\"displayPoints\": false,
				\"pointColor\": \"#7AC943FF\",
				\"pointSize\": 1.0,
				\"availability\" : 0,
				\"value\" : 0
			}
		}";

		$game = new Game();
		$globalConfig = $game->GetGameConfigValues();
		
		$region = $globalConfig["region"];
		$config = $globalConfig["SEL"];
		$boundsConfig = $this->CalculateAlignedSimulationBounds($globalConfig);

		foreach($config["heatmap_settings"] as $heatmap)
		{
			$existingLayer = Database::GetInstance()->query("SELECT layer_id, layer_raster FROM layer WHERE layer_name = ?", array($heatmap["layer_name"]));

			if (count($existingLayer) > 0)
			{
				$rasterData = json_decode($existingLayer[0]["layer_raster"], true);
			}
			else 
			{
				$rasterData = array();
			}

			$rasterData["url"] = $heatmap["layer_name"].".png";
			$rasterData["download_from_geoserver"] = false;
			
			if (isset($heatmap["output_for_mel"]) && $heatmap["output_for_mel"] === true)
			{
				$melConfig = &$globalConfig["MEL"];
				$rasterData["boundingbox"] = array(array($melConfig['x_min'], $melConfig['y_min']), array($melConfig['x_max'], $melConfig['y_max']));
			}
			else 
			{
				$rasterData["boundingbox"] = array(array($boundsConfig['x_min'], $boundsConfig['y_min']), array($boundsConfig['x_max'], $boundsConfig['y_max']));
			}

			$jsonRasterData = json_encode($rasterData);

			if (count($existingLayer) > 0)
			{
				Database::GetInstance()->query("UPDATE layer SET layer_raster = ? WHERE layer_id = ?", array($jsonRasterData, $existingLayer[0]["layer_id"]));
			}
			else 
			{
				Base::Debug("SEL Adding in raster layer " . $heatmap["layer_name"] . " with default values. Defining this layer in the config file will allow you to modify the values.");				
				Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_short, layer_geotype, layer_group, layer_category, layer_subcategory, layer_raster, layer_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", 
					array($heatmap["layer_name"], $heatmap["layer_name"], "raster", $region, "Activities", "Shipping", $jsonRasterData, $defaultEntityTypes)); 
			}
		}
	}

	/**
	 * @apiGroup SEL
	 * @api {GET} /sel/StartExe StartExe
	 * @apiDescription This is a horrible idea...
	 */
	public function StartExe(){
		$this->StartSimulationExe($this->GetStartExeCommand());
	}

	public function GetStartExeCommand() {
		return array("exe" => "SEL.exe", "working_directory" => "simulations/SEL/");
	}

	public function GetKPIDefinition()
	{
		$config = $this->GetSELConfigInternal();
		$result = Array();
		
		if ($config != null && array_key_exists("shipping_kpi_config", $config))
		{
			$categories = $config["shipping_kpi_config"];
			foreach($categories as $categoryDefinition)
			{
				$category = $categoryDefinition;
				if (!array_key_exists("valueDefinitions", $category))
				{
					$category["valueDefinitions"] = array();				
				}

				if (array_key_exists("generateValuesPerPort", $categoryDefinition) && !empty($categoryDefinition["generateValuesPerPort"]))
				{
					$category["countryDependentValues"] = true;

					//Generate values for each port using the pattern provided by the field.
					$graphZero = 0;
					if (array_key_exists("graphZero", $categoryDefinition))
					{
						$graphZero = $categoryDefinition["graphZero"];
					}

					$graphOne = 1500;
					if (array_key_exists("graphOne", $categoryDefinition))
					{
						$graphOne = $categoryDefinition["graphOne"];
					}

					$unit = "";
					if (array_key_exists("unit", $categoryDefinition))
					{
						$unit = $categoryDefinition["unit"];
					}

					$portNames = $this->GetDefinedShippingPortNamesAndOwningCountry();
					$portsWithIntensity = $this->GetPortsWithDefinedIntensities();
					$kpiPortNames = array_uintersect($portNames, $portsWithIntensity, function($lhs, $rhs) {
						//Php apparently checks lhs <> lhs, lhs <> rhs, rhs <> rhs.
						return strcasecmp(is_array($lhs)? $lhs["name"] : $lhs, is_array($rhs)? $rhs["name"] : $rhs);
					});
					foreach($kpiPortNames as $portName)
					{
						$kpiValue["valueName"] = $categoryDefinition["generateValuesPerPort"].$portName["name"];
						$kpiValue["valueDisplayName"] = $portName["name"];
						$kpiValue["valueColor"] = "0x0000ff";
						$kpiValue["graphZero"] = $graphZero;
						$kpiValue["graphOne"] = $graphOne;
						$kpiValue["unit"] = $unit;
						$kpiValue["valueDependentCountry"] = ($portName["country_id"] != null)? $portName["country_id"] : 0;

						$category["valueDefinitions"][] = $kpiValue;
					}
				}

				$result[] = $category;
			}
		}

		return $result;
	}

	private function GetPortsWithDefinedIntensities()
	{
		$portIntensityValues = $this->GetSELConfigInternal()["port_intensity"];
		$result = array();
		foreach($portIntensityValues as $portIntensity)
		{
			if (!empty($portIntensity["ship_intensity_values"]))
			{
				$result[] = $portIntensity["port_id"];
			}
		}
		return $result;
	}

	private function GetDefinedShippingPortNamesAndOwningCountry()
	{
		$portLayers = $this->GetSELConfigInternal()["port_layers"];

		$result = array();
		foreach($portLayers as $portLayer)
		{
			if ($portLayer['port_type'] != "DefinedPort")
			{
				continue;
			}

			//As soon as we have plans that can specify ports this query needs to change to include those plans.
			$data = Database::GetInstance()->query("SELECT geometry.geometry_data, geometry.geometry_country_id FROM geometry
					LEFT JOIN layer ON geometry.geometry_layer_id = layer.layer_id 
				WHERE layer.layer_name = ? ", array($portLayer['layer_name']));

			foreach($data as $d)
			{
				$portData = array_change_key_case(json_decode($d["geometry_data"], true), CASE_LOWER);
				if (array_key_exists("name", $portData))
				{
					$result[] = array("name" => $portData["name"], "country_id" => $d["geometry_country_id"]);
				}
			}

			usort($result, function($lhs, $rhs) { 
				return strcmp($lhs["name"], $rhs["name"]);
			});
		}

		return $result;
	}

	/**
	 * @apiGroup SEL
	 * @api {GET} /sel/NotifyUpdateFinished Notify Update Finished
	 * @apiParams month The month this update was completed for.
	 * @apiDescription Notifies the server that SEL has finished the update for this month. 
	 */
	public function NotifyUpdateFinished(int $month)
	{
		Database::GetInstance()->query("UPDATE game SET game_sel_lastmonth = ?", array($month));
	}

	/**
	 * @apiGroup SEL 
	 * @api {GET} /sel/GetUpdatePackage Get update package
	 * @apiDescription Get an update package which describes whan needs to be updated in the SEL program.
	 */
	public function GetUpdatePackage()
	{
		$time = Database::GetInstance()->query('SELECT game_currentmonth FROM game')[0]['game_currentmonth'];
		
		$result = array("rebuild_edges" => $this->HaveInterestedLayersChangedInMonth($time));
		if ($time == 0 && $result["rebuild_edges"] == false) 
		{
			//Make sure we double check this for month -1 when exiting the setup phase.
			$result["rebuild_edges"] = $this->HaveInterestedLayersChangedInMonth(-1);
		}

		return $result;
	}

	private function HaveInterestedLayersChangedInMonth($month)
	{
		$config = $this->GetSELConfigInternal();

		$shippingLayers = $config["shipping_lane_layers"];

		$interestedLayers = array();

		foreach($shippingLayers as $shippingLaneLayer)
		{
			$shippingLayerData = Database::GetInstance()->query("SELECT layer_id FROM layer WHERE layer_name = ?", array($shippingLaneLayer));
			$interestedLayers[] = $shippingLayerData[0]['layer_id'];

			$interestedLayers = array_merge($interestedLayers, $this->GetRestrictionLayersForLayer($shippingLaneLayer));
		}
		$interestedLayers = array_unique($interestedLayers);

		//If we have a plan that is implemented on any of the layers contained in interestedLayers we need to rebuild the lanes.
		$result = false;
		foreach($interestedLayers as $layerId)
		{
			//But we only care about layers that are referenced in the plan and are applied to our original layers.
			$layerResult = Database::GetInstance()->query("SELECT COUNT(plan.plan_id) as count
				FROM plan
				INNER JOIN plan_layer ON plan.plan_id = plan_layer.plan_layer_plan_id
				INNER JOIN layer ON plan_layer.plan_layer_layer_id = layer.layer_id
				WHERE layer.layer_original_id = ? AND plan.plan_state = \"IMPLEMENTED\" AND plan.plan_gametime = ?", array($layerId, $month));

			if ($layerResult[0]['count'] > 0)
			{
				$result = true;
				break;
			}
		}
		return $result;
	}

	/**
	 * @apiGroup SEL 
	 * @api {GET} /sel/GetSELGameClientConfig Get Game Client Config
	 * @apiDescription Returns a json object of game-client specific settings related to shipping.
	 */
	public function GetSELGameClientConfig()
	{
		$config = $this->GetSELConfigInternal();
		if ($config != null && array_key_exists("game_client_settings", $config))
		{
			return $config["game_client_settings"];
		}
		else
		{
			return new stdClass(); //"{}";
		}
	}
}