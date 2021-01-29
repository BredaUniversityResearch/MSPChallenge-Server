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
		 * @apiParam {int} month Month that this KPI applies to.
		 * @apiParam {int} value the value of this months kpi
		 * @apiParam {string} type the type of KPI (ECOLOGY, ENERGY, SHIPPING)
		 * @apiParam {string} unit the measurement unit of this KPI
		 * @apiParam {int} country (OPTIONAL) id of the country that this belongs to. Not filling this in will default it to all countries
		 * @apiDescription Add a new kpi value to the database
		 */
		public function Post(string $name, int $month, int $value, string $type, string $unit, int $country = -1)
		{
			return $this->PostKPI($name, $month, $value, $type, $unit, $country);
		}

		/**
		 * @apiGroup KPI
		 * @api {GET} /kpi/BatchPost BatchPost
		 * @apiParam {array} kpiValues Input format should be [{"name":(string kpiName),"month": (int month), "value":(int kpiValue),"type":(string kpiType),"unit":(string kpiUnit),"country":(int countryId or null)}] 
		 * @apiDescription Add a new kpi value to the database
		 */
		public function BatchPost(array $kpiValues)
		{
			foreach($kpiValues as $value)
			{
				$this->PostKPI($value["name"], $value["month"], $value["value"], $value["type"], $value["unit"], $value["country"]);
			}
		}

		private function PostKPI(string $kpiName, int $kpiMonth, int $kpiValue, string $kpiType, string $kpiUnit, int $kpiCountry = -1)
		{
			$value = floatval(str_replace(",", ".", $kpiValue));
			return Database::GetInstance()->query("INSERT INTO kpi (kpi_name, kpi_value, kpi_month, kpi_type, kpi_lastupdate, kpi_unit, kpi_country_id) 
					VALUES (?, ?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE kpi_value = ?, kpi_lastupdate = ?", 
				array($kpiName, $value, $kpiMonth, $kpiType, microtime(true), $kpiUnit, $kpiCountry, $value, microtime(true)), true
			);
		}

		public function Latest(int $time, int $country)
		{
			$data = array();

			//should probably be renamed to be something other than ecology
			$data['ecology'] = Database::GetInstance()->query("SELECT 
				kpi_name as name,
				kpi_value as value,
				kpi_month as month,
				kpi_type as type,
				kpi_lastupdate as lastupdate,
				kpi_country_id as country
			FROM kpi WHERE kpi_lastupdate>? AND (kpi_country_id=? OR kpi_country_id = -1) AND kpi_type=\"ECOLOGY\"", array($time, $country));

			$data['shipping'] = Database::GetInstance()->query("SELECT 
				kpi_name as name,
				kpi_value as value,
				kpi_month as month,
				kpi_type as type,
				kpi_lastupdate as lastupdate,
				kpi_country_id as country
			FROM kpi WHERE kpi_lastupdate>? AND (kpi_country_id=? OR kpi_country_id = -1) AND kpi_type=\"SHIPPING\"", array($time, $country));


			$data['energy'] = Database::GetInstance()->query("SELECT 
				energy_kpi_grid_id as grid,
				energy_kpi_month as month,
				energy_kpi_country_id as country,
				energy_kpi_actual as actual
			FROM energy_kpi WHERE energy_kpi_lastupdate>?", array($time));
			return $data;
		}
	}
?>