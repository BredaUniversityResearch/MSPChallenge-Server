<?php

namespace App\Domain\API\v1;

use Exception;
use React\Promise\PromiseInterface;

class Objective extends Base
{
    private const ALLOWED = array(
        "Post",
        "Delete",
        "SetCompleted",
        "Export",
        "Import"
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup Objective
     * @throws Exception
     * @api {POST} /objective/post Post
     * @apiParam {int} country country id, set -1 if you want to add an objective to all countries
     * @apiParam {string} title objective title
     * @apiParam {string} description objective description
     * @apiParam {int} deadline game month when this task needs to be completed by
     * @apiParam {string} tasks JSON array with task objects. Example:
     *   [{"sectorname":"","category":"","subcategory":"","function":"","value":0,"description":""}]
     * @apiDescription Add a new objective with tasks to a country (or all countries at once)
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Post(
        int $country,
        string $title,
        string $description,
        int $deadline,
        string $tasks = '[]'
    ): void {
        $obj = json_decode($tasks, true);
                        
        $countries = array();
        if ($country != -1) {
            array_push($countries, $country);
        } else {
            $data = $this->getDatabase()->query("SELECT country_id FROM country WHERE country_is_manager = 0");

            foreach ($data as $d) {
                array_push($countries, $d['country_id']);
            }
        }

        foreach ($countries as $country) {
            $id = $this->getDatabase()->query(
                "
                INSERT INTO objective (
                    objective_country_id, objective_title, objective_description, objective_deadline,
                    objective_lastupdate
                ) VALUES (?, ?, ?, ?, ?)
                ",
                array($country, $title, $description, $deadline, microtime(true)),
                true
            );

            foreach ($obj as $task) {
                if (array_key_exists("sectorname", $task)) {
                    $sectorName = $task["sectorname"];
                } else {
                    $sectorName = null;
                }

                $this->getDatabase()->query(
                    "
                    INSERT INTO task (
                        task_objective_id, task_sectorname, task_category, task_subcategory, task_function, task_value,
                        task_description
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ",
                    array(
                        $id, $sectorName, $task['category'], $task['subcategory'], $task['function'], $task['value'],
                        $task['description']
                    )
                );
            }
        }
    }

    /**
     * @apiGroup Objective
     * @throws Exception
     * @api {POST} /objective/delete Delete
     * @apiParam {int} id id of the objective to delete
     * @apiDescription Set an objective to be inactive
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Delete(int $id): void
    {
        $this->getDatabase()->query(
            "UPDATE objective SET objective_active=?, objective_lastupdate=? WHERE objective_id=?",
            array(0, microtime(true), $id)
        );
    }

    /**
     * @apiGroup Objective
     * @throws Exception
     * @api {POST} /objective/SetCompleted SetCompleted
     * @apiParam {int} objective_id id of the objective to set the completed state for
     * @apiParam {int} completed State (0 or 1) of the completed flag to set
     * @apiDescription Changes the completed state of an objective.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetCompleted(int $objective_id, int $completed): void
    {
        $this->getDatabase()->query(
            "UPDATE objective SET objective_complete = ?, objective_lastupdate = ? WHERE objective_id = ?",
            array($completed, microtime(true), $objective_id)
        );
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Export(array &$configObject): void
    {
        // move to deprecate - was used in the old tools class only as far as I know (Harald)
        $objectives = $this->getDatabase()->query("SELECT objective_id, 
					objective_country_id as country_id, 
					objective_title as title, 
					objective_description as description, 
					objective_deadline as deadline 
				FROM objective WHERE objective_active = 1");

        $configObject['objectives'] = $objectives;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Import(): void
    {
        $game = new Game();
        $config = $game->GetGameConfigValues();

        if (!isset($config['objectives'])) {
            return;
        }

        foreach ($config['objectives'] as $objective) {
            $this->getDatabase()->query(
                "
                INSERT INTO objective (
                    objective_country_id, objective_title, objective_description, objective_deadline,
                    objective_lastupdate
                ) VALUES (?, ?, ?, ?, 100)
                ",
                array($objective['country_id'], $objective['title'], $objective['description'], $objective['deadline']),
                true
            );
        }
    }
}
