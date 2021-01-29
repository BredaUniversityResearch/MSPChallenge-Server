<?php

class REL extends Base
{
	protected $allowed = array(
		"GetRestrictionGeometry",
		"GetConfiguration"
	);

	public function __construct($method = "")
	{
		parent::__construct($method);
	}

	/**
	* @apiGroup REL
	* @api {GET} /rel/GetRestrictionGeometry GetRestrictionGeometry
	* @apiDescription Returns all restriction geometry that appears on a configured restriction layer. Geometry_type is translated into geometry type that corresponds to the type configured and communicated to Marin API.
	*/
	public function GetRestrictionGeometry()
	{
		//TODO: Check with Marin what  we should send here... Just windfarms or all of the geometry that SEL uses...
		$result = Database::GetInstance()->query("SELECT geometry_id as geometry_id,
			geometry_geometry as geometry, 
			geometry_type as geometry_type
			FROM geometry
			WHERE geometry_layer_id IN (SELECT layer_id FROM layer WHERE layer_name LIKE ?)", array("NS_Wind_Farms_Implemented"));
		
		foreach($result as &$val)
		{
			$val["geometry"] = json_decode($val["geometry"]);
		}
			
		return $result;
	}

	/**
	* @apiGroup REL
	* @api {GET} /rel/GetConfiguration GetConfiguration
	* @apiDescription Returns object containing configuration values for REL.
	*/
	public function GetConfiguration()
	{
		$config = $this->GetRELConfigValues();
		if ($config != null)
		{
			return $config;
		}
		return null;
	}

	private function GetRELConfigValues()
	{
		$game = new Game();
		$config = $game->GetGameConfigValues();
		if (isset($config["REL"]))
		{
			return $config["REL"];
		}
		return null;
	}

	public function OnReimport()
	{
		$config = $this->GetRELConfigValues();
		if ($config != null)
		{
			$this->SetupRasterLayer($config["contacts_output_layer"]);
			$this->SetupRasterLayer($config["collision_output_layer"]);
		}
		else
		{
			Base::Debug("REL config values not set, but reimport is called on REL class... Session will be all kinds of broken when it comes to REL");
		}
	}

	private function SetupRasterLayer($rasterLayerName)
	{
		$rasterLayerData = [];
		$rasterLayerData["layer_name"] = $rasterLayerName;

		$existingLayer = Database::GetInstance()->query("SELECT layer_id, layer_raster FROM layer WHERE layer_name = ?", array($rasterLayerData["layer_name"]));
		if (count($existingLayer) > 0)
		{
			$rasterData = json_decode($existingLayer[0]["layer_raster"], true);
		}
		else 
		{
			$rasterData = $rasterLayerData;
		}

		$rasterData['download_from_geoserver'] = false;
		$rasterData["url"] = $rasterLayerName.".png";

		$jsonRasterData = json_encode($rasterData);

		if (count($existingLayer) > 0)
		{
			Database::GetInstance()->query("UPDATE layer SET layer_raster = ? WHERE layer_id = ?", array($jsonRasterData, $existingLayer[0]["layer_id"]));
		}
		else
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

			Base::Debug("REL Adding in raster layer " . $rasterData["layer_name"] . " with default values. Defining this layer in the config file will allow you to modify the values.");				
			Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_short, layer_geotype, layer_category, layer_subcategory, layer_raster, layer_type) VALUES (?, ?, ?, ?, ?, ?, ?)", 
				array($rasterData["layer_name"], $rasterData["layer_name"], "raster", "Activities", "Shipping", $jsonRasterData, $defaultEntityTypes)); 
		}
	}
};

?>