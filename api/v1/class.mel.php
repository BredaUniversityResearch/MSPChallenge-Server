<?php
	class Mel extends Base{
		
		protected $allowed = array(
			"OnReimport", 
			"Config", 
			"UpdateLayer", 
			"ShouldUpdate", 
			"Update", 
			"TickDone", 
			"GetFishing", 
			"GeometryExportName", 
			"InitialFishing"
		);

		
		public function __construct($method = ""){
			parent::__construct($method);
		}

		public function Config(){
			$game = new Game();
			$tmp = $game->GetGameConfigValues();
			if (isset($tmp['MEL'])) {
				return $tmp['MEL'];
			}
			return null;
		}

		public function OnReimport(array $config){
			//wipe the table for testing purposes
			Database::GetInstance()->query("TRUNCATE TABLE mel_layer");
			Database::GetInstance()->query("TRUNCATE TABLE fishing");

			//Check the config file.
			if (isset($config["fishing"])) {
				$countries = Database::GetInstance()->query("SELECT * FROM country WHERE country_is_manager = 0");
				foreach($config["fishing"] as $fleet) {
					if (isset($fleet["initialFishingDistribution"])) {
						foreach($countries as $country) { 
							$foundCountry = false;
							foreach($fleet["initialFishingDistribution"] as $distribution) {
								if ($distribution["country_id"] == $country["country_id"]) {
									$foundCountry = true;
									break;
								}
							}

							if (!$foundCountry) {
								throw new Exception("Country with ID ".$country["country_id"]." is missing a distribution entry in the initialFishingDistribution table for fleet ".$fleet["name"]." for MEL.");
							}
						}
					}
				}
			}

			foreach($config['pressures'] as $pressure){
				$pressureId = $this->SetupMELLayer($pressure['name'], $config);

				if ($pressureId != -1) {
					foreach($pressure['layers'] as $layer){	
						$layerid = Database::GetInstance()->query("SELECT layer_id FROM layer WHERE layer_name=?", array($layer['name']));
						if(!empty($layerid)){
							$layerid = $layerid[0]['layer_id'];
							
							$mellayer = Database::GetInstance()->query("SELECT mel_layer_id FROM mel_layer WHERE mel_layer_pressurelayer=? AND mel_layer_layer_id=?", array($pressureId, $layerid));
							if(empty($mellayer)){
								//add a layer to the mel_layer table for faster accessing
								Database::GetInstance()->query("INSERT INTO mel_layer (mel_layer_pressurelayer, mel_layer_layer_id) VALUES (?, ?)", array($pressureId, $layerid));
							}
						}
					}
				}
			}

			foreach($config['outcomes'] as $outcome){
				$this->SetupMELLayer($outcome['name'], $config);
			}
		}

		private function SetupMELLayer(string $melLayerName, array $config) 
		{
			$layername = "mel_" . str_replace(" ", "_", $melLayerName);			
			$data = Database::GetInstance()->query("SELECT layer_id, layer_raster FROM layer WHERE layer_name=?", array($layername));
				
			$rasterProperties = array("url" => "$layername.tif",
				"boundingbox" => array(array($config["x_min"], $config["y_min"]), array($config["x_max"], $config["y_max"])));

			$layerId = -1;
			if(empty($data)) {
				//create new layer
				$rasterformat = json_encode($rasterProperties);
				$layerId = Database::GetInstance()->query("INSERT INTO layer (layer_name, layer_short, layer_geotype, layer_group, layer_category, layer_subcategory, layer_raster) VALUES (?, ?, ?, ?, ?, ?, ?)", 
					array($layername, $melLayerName, "raster", $config['region'], "Ecology", "pressure", $rasterformat), true
				);
			}
			else {
				$layerId = $data[0]['layer_id'];
				$existingRasterProperties = json_decode($data[0]['layer_raster'], true);
				$rasterProperties = array_merge($existingRasterProperties ?? array(), $rasterProperties);
				$rasterformat = json_encode($rasterProperties);
				Database::GetInstance()->query("UPDATE layer SET layer_raster=? WHERE layer_id = ?", array($rasterformat, $layerId));
			}
			return $layerId;
		}

		public function InitialFishing(array $fishing_values)
		{
			$existingPlans = Database::GetInstance()->query("SELECT plan.plan_id FROM plan WHERE plan.plan_gametime = -1 AND plan.plan_type LIKE \"_,1,_\"");
			if (count($existingPlans) > 0) {
				//In this case we already have something in the database that is a fishing plan, might be of a previous instance of MEL on this session or a starting plan. 
				//Don't insert any new values in the database to avoid the fishing values increasing every start of MEL.
				return;
			}

			$countries = Database::GetInstance()->query("SELECT country_id FROM country WHERE country_is_manager != 1");
			$numCountries = count($countries);

			$planid = Database::GetInstance()->query("INSERT INTO plan (plan_name, plan_country_id, plan_gametime, plan_state, plan_type) VALUES (?, ?, ?, ?, ?)", 
				array("FISHING_STARTING_PLAN", 1, -1, "IMPLEMENTED", "0,1,0"), true
			);

			$config = $this->Config();
			$weightsByFleet = array();
			if (isset($config["fishing"])) {
				$fishingFleets = $config["fishing"];
				foreach($fishingFleets as $fishingFleet) {
					$weightsByCountry = array();

					if (isset($fishingFleet["initialFishingDistribution"])) {
						$fishingValues = $fishingFleet["initialFishingDistribution"];
					
						//We need to average the weights over the available countries
						$sum = 0.0;
						foreach($fishingValues as $val) {
							if (isset($val["weight"]) && isset($val["country_id"])) {
								$sum += $val["weight"];
								$weightsByCountry[$val["country_id"]] = $val["weight"];
							}
						}
					
						$weightMultiplier = ($sum > 0)? 1.0 / $sum : 1.0 / $numCountries;
						foreach($weightsByCountry as &$countryWeight)
						{
							$countryWeight *= $weightMultiplier;
						}

						$weightsByFleet[$fishingFleet["name"]] = $weightsByCountry;
					}
				}
			}

			foreach($fishing_values as $fishing) {
				$name = $fishing["fleet_name"];

				foreach($countries as $country){
					$countryId = $country["country_id"];
					if (isset($weightsByFleet[$name][$countryId])) {
						$weight = $weightsByFleet[$name][$countryId];
					}
					else { 
						$weight = 0.1;
					}

					Database::GetInstance()->query("INSERT INTO fishing (fishing_country_id, fishing_plan_id, fishing_type, fishing_amount, fishing_active) VALUES (?, ?, ?, ?, ?)", 
						array($country['country_id'], $planid, $name, $fishing["fishing_value"] * $weight, 1)
					);
				}
			}
		}

		public function UpdateLayer(string $layer_name)
		{			
			Database::GetInstance()->query("UPDATE layer SET layer_lastupdate=? WHERE layer_name=?", array(microtime(true), $layer_name));
		}

		public function Update()
		{
			$r = Database::GetInstance()->query("SELECT layer_name, layer_melupdate_construction FROM layer WHERE layer_melupdate=?", array(1));
			
			$layers = [];
			foreach($r as $l){
				// if($l['layer_melupdate_construction'] == 1){
				// 	$str .= $l['layer_name'] . "(c,";
				// }
				// else{
					$layers[] = $l['layer_name'];
				// }
			}

			Database::GetInstance()->query("UPDATE layer SET layer_melupdate=0");

			return $layers;
		}

		public function Latest($time){
		}

		public function ShouldUpdate(int $mel_month){
			$game = new Game();
			$currentMonth = $game->GetCurrentMonthAsId();

			if($mel_month < $currentMonth){
				return $currentMonth; //was echoed
			}
			else{
				return -100; //was echoed
			}
		}

		public function TickDone(){
			Database::GetInstance()->query("UPDATE game SET game_mel_lastmonth=game_currentmonth");
		}

		public function GetFishing(int $game_month){
			$data = Database::GetInstance()->query("SELECT SUM(fishing_amount) as scalar, fishing_type as name FROM fishing 
									LEFT JOIN plan ON plan.plan_id=fishing.fishing_plan_id
									WHERE fishing_active = 1 AND plan_gametime <= ?
									GROUP BY fishing_type", 
									array($game_month));
				
			//Make sure fishing scalars never exceed 1.0
			foreach($data as &$fishingValues) { 
				if (floatval($fishingValues['scalar']) > 1.0) {
					$fishingValues['scalar'] = 1.0;
				}
			}

			return $data;
		}

		/**
		 * @apiGroup MEL
		 * @apiDescription Gets all the geometry data of a layer
		 * @api {POST} /mel/GeometryExportName Geometry Export Name
		 * @apiParam {string} layer name to return the geometry data for
		 * @apiParam {int} layer_type type within the layer to return. -1 for all types.
		 * @apiParam {bool} construction_only whether or not to return data only if it's being constructed.
		 * @apiSuccess {string} JSON JSON Object
		 */
		public function GeometryExportName(string $name, int $layer_type = -1, bool $construction_only = false) {

			$id = Database::GetInstance()->query("SELECT layer_id, layer_geotype, layer_raster FROM layer WHERE layer_name=?", array($name));

			$result = array("geotype" => "");
			
			if(!empty($id)){
				$original = $id[0]['layer_id'];
				
				if($id[0]['layer_geotype'] == "raster"){
					$rasterjson = json_decode($id[0]['layer_raster']);

					$result["geotype"] = $id[0]['layer_geotype'];
					$result["raster"] = $rasterjson->url;

					return $result;
				}
				else{
					$arguments = array($original, $original);
					$planStateSelector = "(plan_layer_state=\"ACTIVE\" OR plan_layer_state IS NULL)";
					$optionalStatements = "";

					if ($layer_type != -1) {
						$optionalStatements = " AND geometry_type LIKE ?";
						$arguments[] =  "%" . $layer_type . "%";
					}
					if ($construction_only) {
						$planStateSelector = "plan_layer_state=\"ASSEMBLY\"";
					}

					$geometry = Database::GetInstance()->query("SELECT layer_id, geometry_geometry as g FROM layer 
						LEFT JOIN plan_layer ON plan_layer_layer_id=layer_id
						LEFT JOIN geometry ON geometry_layer_id=layer_id
						WHERE (layer_id=? OR layer_original_id=?) AND ".$planStateSelector." AND geometry_active=1".$optionalStatements, $arguments);

					$result["geotype"] = $id[0]['layer_geotype'];
					$result["geometry"] = array();

					foreach($geometry as $geom){
						if(!empty($geom['g'])) {
							$result["geometry"][] = json_decode($geom['g']);
						}
					}
					return $result;
				}
			}

			return null;
		}
	}
?>