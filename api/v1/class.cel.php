<?php
	class Cel extends Base{
		protected $allowed = array(
			"GetConnections", 
			"GetCELConfig", 
			"GetGrids", 
			"GetNodes", 
			"GetSources",
			"SetGeomCapacity", 
			"SetGridCapacity", 
			"StartExe", 
			"ShouldUpdate", 
			"UpdateFinished"
		);

		
		public function __construct($method = ""){
			parent::__construct($method);
		}

		//CEL Input queries
		/**
		 * @apiGroup Cel
		 * @api {POST} /cel/GetConnections GetConnections
		 * @apiDescription Get all active energy connections
		 */
		public function GetConnections(){

			$data = Database::GetInstance()->query("SELECT 
					energy_connection_start_id as fromNodeID,
					energy_connection_end_id as toNodeID,
					energy_connection_cable_id as cableID,
					energy_output_maxcapacity as maxcapacity

				FROM energy_connection 
					LEFT JOIN geometry ON energy_connection.energy_connection_cable_id=geometry.geometry_id
					LEFT JOIN layer ON geometry.geometry_layer_id=layer.layer_id
					LEFT JOIN plan_layer ON layer.layer_id=plan_layer.plan_layer_layer_id
					LEFT JOIN plan ON plan_layer.plan_layer_plan_id=plan.plan_id
					LEFT JOIN energy_output ON energy_connection_cable_id=energy_output_geometry_id
				WHERE energy_connection_active=? AND plan_state=? AND geometry_active=?", array(1, "IMPLEMENTED", 1));

			return $data;	

			//add energy_output
		}

		/**
		 * @apiGroup Cel
		 * @api {GET} /cel/GetCELConfig Get Config 
		 * @apiDescription Returns the Json encoded config string
		 */
		public function GetCELConfig() 
		{
			$game = new Game();
			$tmp = $game->GetGameConfigValues();
			if (array_key_exists("CEL", $tmp))
			{
				return $tmp["CEL"];
			}
			else 
			{
				return new stdClass();
			}
		}

		/**
		 * @apiGroup Cel
		 * @api {GET} /cel/ShouldUpdate Should Update
		 * @apiDescription Should Cel update this month?
		 */
		public function ShouldUpdate() {
			$time = Database::GetInstance()->query('SELECT game_currentmonth FROM game')[0];
			if ($time['game_currentmonth'] == 0) { //Yay starting plans.
				return true;
			}

			$implementedPlans = Database::GetInstance()->query("SELECT plan_type FROM plan WHERE plan_gametime = ? AND plan_state = \"IMPLEMENTED\" AND plan_type LIKE \"1,_,_\"", array($time['game_currentmonth']));
			return (count($implementedPlans) > 0);
		}

		/**
		 * @apiGroup Cel
		 * @api {POST} /cel/UpdateFinished Update Finished
		 * @apiParam {int} month The month Cel just finished an update for. 
		 * @apiDescription Notify that Cel has finished updating a month
		 */
		public function UpdateFinished(int $month) {
			Database::GetInstance()->query("UPDATE game SET game_cel_lastmonth = ?", array($month));
		}

		/**
		 * @apiGroup Cel
		 * @api {POST} /cel/GetNodes Get Nodes
		 * @apiDescription Get all nodes that have an output associated with them
		 */
		public function GetNodes(){
			$data = Database::GetInstance()->query("SELECT 
				energy_output_geometry_id as geometry_id, 
				energy_output_maxcapacity as maxcapacity
				FROM energy_output
				LEFT JOIN geometry ON energy_output.energy_output_geometry_id=geometry.geometry_id
				LEFT JOIN layer l ON geometry.geometry_layer_id=l.layer_id
				LEFT JOIN plan_layer ON l.layer_id=plan_layer.plan_layer_layer_id
				LEFT JOIN plan ON plan_layer.plan_layer_plan_id=plan.plan_id
				LEFT JOIN layer original ON original.layer_id=l.layer_original_id
				WHERE energy_output_active=? AND plan_state=? AND geometry_active=? AND original.layer_geotype<>?", array(1, "IMPLEMENTED", 1, "line"));

			
			return $data;
		}

		/**
		 * @apiGroup Cel
		 * @api {POST} /cel/GetSources Get Sources
		 * @apiDescription Returns a list of all active sources
		 */
		public function GetSources(){
			$data = Database::GetInstance()->query("SELECT grid_source.grid_source_geometry_id FROM grid
				INNER JOIN grid_source ON grid_source.grid_source_grid_id = grid.grid_id
				LEFT JOIN plan ON grid.grid_plan_id = plan.plan_id
				LEFT JOIN geometry on grid_source.grid_source_geometry_id = geometry.geometry_id
			WHERE grid_active = 1 AND geometry_active=1 AND (plan.plan_state IS NULL OR plan.plan_state = \"IMPLEMENTED\")", array());

			$arr = array();

			foreach($data as $d){
				if($d['grid_source_geometry_id'] != null)
					array_push($arr, $d['grid_source_geometry_id']);
			}

			return $arr;
		}

		/**
		 * @apiGroup Cel
		 * @api {POST} /cel/GetGrids Get Grids
		 * @apiDescription Get all grids and their associated sockets, sorted per country
		 */
		public function GetGrids(){
			$data = Database::GetInstance()->query("SELECT grid.grid_id, grid_energy.grid_energy_country_id, grid_energy.grid_energy_expected, geometry.geometry_id FROM grid_socket 
					LEFT JOIN grid ON grid_socket_grid_id=grid_id
					LEFT JOIN geometry on grid_socket_geometry_id = geometry_id
					LEFT JOIN grid_energy ON grid_energy_country_id = geometry.geometry_country_id
					LEFT JOIN plan ON grid.grid_plan_id = plan.plan_id
				WHERE grid_active = 1 AND grid_energy_grid_id = grid_id AND geometry.geometry_active = 1 AND (plan.plan_state IS NULL OR plan.plan_state = \"IMPLEMENTED\")");

			$obj = array();

			foreach($data as $d){
				$id = $d['grid_id'];
				$country = $d['grid_energy_country_id'];

				if(!isset($obj[$id])){
					$obj[$id] = array();
					$obj[$id]["grid"] = $id;
					$obj[$id]["energy"] = array();
				}

				if(!isset($obj[$id]["energy"][$country])){
					$obj[$id]["energy"][$country] = array();
					$obj[$id]["energy"][$country]['expected'] = $d['grid_energy_expected'];
					$obj[$id]["energy"][$country]['country'] = $country;
					$obj[$id]["energy"][$country]['sockets'] = array();
				}

				array_push($obj[$id]["energy"][$country]['sockets'], $d['geometry_id']);
			}

			//convert everything to arrays instead of objects
			$robj = array_values($obj);
			foreach($robj as &$r){
				$r['energy'] = array_values($r['energy']);
			}

			return $robj;
		}
		
		/**
		 * @apiGroup Cel
		 * @api {POST} /cel/SetGeomCapacity Set Geometry Capacity
		 * @apiDescription Set the energy capacity of a specific geometry object
		 * @apiParam {string} geomCapacityValues Json Encoded string in the format [ { "id" : GRID_ID, "capacity": CAPACITY_VALUE }] 
		 * @apiParam {int} capacity capacity of node
		 */
		public function SetGeomCapacity(string $geomCapacityValues){
			$values = json_decode($geomCapacityValues, true);
			foreach($values as $value)
			{
				Database::GetInstance()->query("UPDATE energy_output SET energy_output_capacity=?, energy_output_lastupdate=? WHERE energy_output_geometry_id=?", array($value['capacity'], microtime(true), $value['id']));
			}
		}

		/**
		 * @apiGroup Cel
		 * @api {POST} /cel/SetGridCapacity Set Grid Capacity
		 * @apiDescription Set the energy capacity of a grid per country, uses the server month time
		 * @apiParam {string} kpiValues Json encoded string in the format [ { "grid": GRID_ID, "actual": ACTUAL_ENERGY_VALUE, "country": COUNTRY_ID } ]
		 */

		public function SetGridCapacity(string $kpiValues){

			$gameMonth = Database::GetInstance()->query("SELECT game_currentmonth FROM game WHERE game_id = 1")[0]["game_currentmonth"];

			$timestamp = microtime(true);

			$values = json_decode($kpiValues, true);
			foreach($values as $value)
			{
				Database::GetInstance()->query("INSERT INTO energy_kpi (energy_kpi_grid_id, energy_kpi_month, energy_kpi_country_id, energy_kpi_actual, energy_kpi_lastupdate) 
						VALUES (?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE energy_kpi_actual = ?, energy_kpi_lastupdate = ?", 
				array($value['grid'], $gameMonth, $value['country'], $value['actual'], $timestamp, $value['actual'], $timestamp));
			}
		}
	}
?>