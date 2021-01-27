<?php
	class Kpi extends Base {

		protected $allowed = array(
			"Post", 
			"BatchPost", 
			"Latest"
		);

		/**
		 * @apiGroup KPI
		 * @api {GET} /kpi/post Post
		 * @apiParam {string} name name of the KPI
		 * @apiParam {int} value the value of this months kpi
		 * @apiParam {string} type the type of KPI (ECOLOGY, ENERGY, SHIPPING)
		 * @apiParam {string} unit the measurement unit of this KPI
		 * @apiParam {int} country (OPTIONAL) id of the country that this belongs to. Not filling this in will default it to all countries
		 * @apiDescription Add a new kpi value to the database
		 */
		public function Post(string $name, int $value, string $type, string $unit, int $country = -1)
		{
			//not passing the month from the simulations means the server is more authorative, maybe the month should be passed by the sims? 
			//This would allow a desync between a simulation and the server

			return $this->PostKPI($name, $value, $type, $unit, $country);
		}

		/**
		 * @apiGroup KPI
		 * @api {GET} /kpi/post Post
		 * @apiParam {string[]} kpiValues Json encoded string of the kpi values that we need to insert on the database. Input format should be [{"name":(string kpiName),"value":(int kpiValue),"type":(string kpiType),"unit":(string kpiUnit),"country":(int countryId or null)}] 
		 * @apiDescription Add a new kpi value to the database
		 */
		public function BatchPost($kpiValues)
		{
			$kpiValues = json_decode($kpiValues);
			foreach($kpiValues as $value)
			{
				$this->PostKPI($value->name, $value->value, $value->type, $value->unit, $value->country);
			}
		}

		private function PostKPI($kpiName, $kpiValue, $kpiType, $kpiUnit, $kpiCountry = -1)
		{
			$value = floatval(str_replace(",", ".", $kpiValue));
			return $this->query("INSERT INTO kpi (kpi_name, kpi_value, kpi_month, kpi_type, kpi_lastupdate, kpi_unit, kpi_country_id) 
					VALUES (?, ?, (SELECT game_currentmonth FROM game WHERE game_id=1), ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE kpi_value = ?, kpi_lastupdate = ?", 
				array($kpiName, $value, $kpiType, microtime(true), $kpiUnit, $kpiCountry, $value, microtime(true)), true
			);
		}

		public function Latest(int $time, int $country)
		{
			$data = array();

			//should probably be renamed to be something other than ecology
			$data['ecology'] = $this->query("SELECT 
				kpi_name as name,
				kpi_value as value,
				kpi_month as month,
				kpi_type as type,
				kpi_lastupdate as lastupdate,
				kpi_country_id as country
			FROM kpi WHERE kpi_lastupdate>? AND (kpi_country_id=? OR kpi_country_id = -1) AND kpi_type=\"ECOLOGY\"", array($time, $country));

			$data['shipping'] = $this->query("SELECT 
				kpi_name as name,
				kpi_value as value,
				kpi_month as month,
				kpi_type as type,
				kpi_lastupdate as lastupdate,
				kpi_country_id as country
			FROM kpi WHERE kpi_lastupdate>? AND (kpi_country_id=? OR kpi_country_id = -1) AND kpi_type=\"SHIPPING\"", array($time, $country));


			$data['energy'] = $this->query("SELECT 
				energy_kpi_grid_id as grid,
				energy_kpi_month as month,
				energy_kpi_country_id as country,
				energy_kpi_actual as actual
			FROM energy_kpi WHERE energy_kpi_lastupdate>?", array($time));
			return $data;
		}
	}
?>