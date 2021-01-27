<?php
	class Objective extends Base {
		
		protected $allowed = array(
			"Post", 
			"Delete",
			"SetCompleted",
			"Export", 
			"Import"
		);
		
		public function __construct($method = "")
		{
			parent::__construct($method);
		}

		/**
		 * @apiGroup Objective
		 * @api {POST} /objective/post Post
		 * @apiParam {int} country country id, set -1 if you want to add an objective to all countries
		 * @apiParam {string} title objective title
		 * @apiParam {string} description objective description
		 * @apiParam {int} deadline game month when this task needs to be completed by
		 * @apiParam {string} tasks JSON array with task objects. Example: [{"sectorname":"","category":"","subcategory":"","function":"","value":0,"description":""}]
		 * @apiDescription Add a new objective with tasks to a country (or all countries at once)
		 */
		public function Post(string $tasks = '[]', int $country, string $title, string $description, int $deadline)
		{
			$obj = json_decode($tasks, true);
						
			$countries = array();
			if($country != -1){
				array_push($countries, $country);
			}
			else{
				$data = $this->query("SELECT country_id FROM country WHERE country_is_manager = 0");

				foreach($data as $d){
					array_push($countries, $d['country_id']);
				}
			}

			foreach($countries as $country){
				$id = $this->query("INSERT INTO objective (objective_country_id, objective_title, objective_description, objective_deadline, objective_lastupdate) VALUES (?, ?, ?, ?, ?)", array($country, $title, $description, $deadline, microtime(true)), true);

				foreach($obj as $task){
					if (array_key_exists("sectorname", $task)) {
						$sectorName = $task["sectorname"];
					}
					else {
						$sectorName = null;
					}

					$this->query("INSERT INTO task (task_objective_id, task_sectorname, task_category, task_subcategory, task_function, task_value, task_description) 
						VALUES (?, ?, ?, ?, ?, ?, ?)", 
						array($id, $sectorName, $task['category'], $task['subcategory'], $task['function'], $task['value'], $task['description']));
				}
			}
		}

		/**
		 * @apiGroup Objective
		 * @api {POST} /objective/delete Delete
		 * @apiParam {int} id id of the objective to delete
		 * @apiDescription Set an objective to be inactive
		 */
		public function Delete(int $id)
		{
			$this->query("UPDATE objective SET objective_active=?, objective_lastupdate=? WHERE objective_id=?", array(0, microtime(true), $id));
		}

		public function Latest($time)
		{
			$data = $this->query("SELECT 
				objective_id, 
				objective_country_id as country_id,
				objective_title as title, 
				objective_description as description, 
				objective_deadline as deadline,
				objective_active as active,
				objective_complete as complete
				FROM objective
				WHERE objective_lastupdate>?", array($time));

			return $data;
		}

		/**
		 * @apiGroup Objective
		 * @api {POST} /objective/SetCompleted SetCompleted
		 * @apiParam {int} objective_id id of the objective to set the completed state for
		 * @apiParam {int} completed State (0 or 1) of the completed flag to set 
		 * @apiDescription Changes the completed state of an objective.
		 */
		public function SetCompleted(int $objective_id, int $completed) 
		{
			$this->query("UPDATE objective SET objective_complete = ?, objective_lastupdate = ? WHERE objective_id = ?", 
				array($completed, microtime(true), $objective_id));
		}

		public function Export(&$configObject)
		{
			// move to deprecate - was used in the old tools class only as far as I know (Harald)
			$objectives = $this->query("SELECT objective_id, 
					objective_country_id as country_id, 
					objective_title as title, 
					objective_description as description, 
					objective_deadline as deadline 
				FROM objective WHERE objective_active = 1");

			$configObject['objectives'] = $objectives;
		}

		public function Import() 
		{
			$game = new Game();
			$config = $game->GetGameConfigValues();

			if (!isset($config['objectives'])) {
				return;
			}

			foreach($config['objectives'] as $objective) {
				$objectiveId = $this->query("INSERT INTO objective (objective_country_id, objective_title, objective_description, objective_deadline, objective_lastupdate) VALUES (?, ?, ?, ?, 100)", 
					array($objective['country_id'], $objective['title'], $objective['description'], $objective['deadline']), true);
			}
		}
	}
?>