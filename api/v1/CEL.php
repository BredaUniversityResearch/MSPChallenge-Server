<?php

namespace App\Domain\API\v1;

use App\Domain\Common\InternalSimulationName;
use App\Domain\Services\ConnectionManager;
use App\Entity\Simulation;
use App\Repository\SimulationRepository;
use Exception;
use stdClass;

class CEL extends Base
{
    //CEL Input queries
    /**
     * @apiGroup Cel
     * @throws Exception
     * @api {POST} /cel/GetConnections GetConnections
     * @apiDescription Get all active energy connections
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetConnections(): array
    {
        return $this->getDatabase()->query("SELECT 
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
    }

    /**
     * @apiGroup Cel
     * @return array|stdClass
     * @throws Exception
     * @api {POST} /cel/GetCELConfig Get Config
     * @apiDescription Returns the Json encoded config string
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetCELConfig(): array|stdClass
    {
        $game = new Game();
        $tmp = $game->GetGameConfigValues();
        if (array_key_exists("CEL", $tmp)) {
            return $tmp["CEL"];
        } else {
            return new stdClass();
        }
    }

    /**
     * @apiGroup Cel
     * @throws Exception
     * @api {POST} /cel/ShouldUpdate Should Update
     * @apiDescription Should Cel update this month?
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ShouldUpdate(): bool
    {
        $time = $this->getDatabase()->query('SELECT game_currentmonth FROM game')[0];
        if ($time['game_currentmonth'] == 0) { //Yay starting plans.
            return true;
        }

        $implementedPlans = $this->getDatabase()->query(
            "
            SELECT plan_type FROM plan
            WHERE plan_gametime = ? AND plan_state = 'IMPLEMENTED' AND (plan_type & ? = ?)
            ",
            array($time['game_currentmonth'], GeneralPolicyType::ENERGY, GeneralPolicyType::ENERGY)
        );
        return (count($implementedPlans) > 0);
    }

    /**
     * @apiGroup Cel
     * @throws Exception
     * @api {POST} /cel/UpdateFinished Update Finished
     * @apiParam {int} month The month Cel just finished an update for.
     * @apiDescription Notify that Cel has finished updating a month
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateFinished(int $month): void
    {
        /** @var SimulationRepository $repo */
        $repo = ConnectionManager::getInstance()->getGameSessionEntityManager($this->getGameSessionId())
            ->getRepository(Simulation::class);
        $repo->notifyMonthFinishedForInternal(InternalSimulationName::CEL, $month);
    }

    /**
     * @apiGroup Cel
     * @throws Exception
     * @api {POST} /cel/GetNodes Get Nodes
     * @apiDescription Get all nodes that have an output associated with them
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetNodes(): array
    {
        return $this->getDatabase()->query(
            "
            SELECT 
				energy_output_geometry_id as geometry_id, 
				energy_output_maxcapacity as maxcapacity
			FROM energy_output
            LEFT JOIN geometry ON energy_output.energy_output_geometry_id=geometry.geometry_id
            LEFT JOIN layer l ON geometry.geometry_layer_id=l.layer_id
            LEFT JOIN plan_layer ON l.layer_id=plan_layer.plan_layer_layer_id
            LEFT JOIN plan ON plan_layer.plan_layer_plan_id=plan.plan_id
            LEFT JOIN layer original ON original.layer_id=l.layer_original_id
            WHERE energy_output_active=? AND plan_state=? AND geometry_active=? AND original.layer_geotype<>?
            ",
            array(1, "IMPLEMENTED", 1, "line")
        );
    }

    /**
     * @apiGroup Cel
     * @throws Exception
     * @api {POST} /cel/GetSources Get Sources
     * @apiDescription Returns a list of all active sources
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetSources(): array
    {
        $data = $this->getDatabase()->query(
            "
            SELECT grid_source.grid_source_geometry_id FROM grid
            INNER JOIN grid_source ON grid_source.grid_source_grid_id = grid.grid_id
            LEFT JOIN plan ON grid.grid_plan_id = plan.plan_id
            LEFT JOIN geometry on grid_source.grid_source_geometry_id = geometry.geometry_id
			WHERE grid_active = 1 AND geometry_active=1 AND
			      (plan.plan_state IS NULL OR plan.plan_state = 'IMPLEMENTED')
			",
            array()
        );

        $arr = array();

        foreach ($data as $d) {
            if ($d['grid_source_geometry_id'] != null) {
                $arr[] = $d['grid_source_geometry_id'];
            }
        }

        return $arr;
    }

    /**
     * @apiGroup Cel
     * @throws Exception
     * @api {POST} /cel/GetGrids Get Grids
     * @apiDescription Get all grids and their associated sockets, sorted per country
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetGrids(): array
    {
        $data = $this->getDatabase()->query(
            "
            SELECT grid.grid_id, grid_energy.grid_energy_country_id, grid_energy.grid_energy_expected,
                   geometry.geometry_id
            FROM grid_socket 
            LEFT JOIN grid ON grid_socket_grid_id=grid_id
            LEFT JOIN geometry on grid_socket_geometry_id = geometry_id
            LEFT JOIN grid_energy ON grid_energy_country_id = geometry.geometry_country_id
            LEFT JOIN plan ON grid.grid_plan_id = plan.plan_id
            WHERE grid_active = 1 AND grid_energy_grid_id = grid_id AND geometry.geometry_active = 1 AND
                  (plan.plan_state IS NULL OR plan.plan_state = 'IMPLEMENTED')
            "
        );

        $obj = array();

        foreach ($data as $d) {
            $id = $d['grid_id'];
            $country = $d['grid_energy_country_id'];

            if (!isset($obj[$id])) {
                $obj[$id] = array();
                $obj[$id]["grid"] = $id;
                $obj[$id]["energy"] = array();
            }

            if (!isset($obj[$id]["energy"][$country])) {
                $obj[$id]["energy"][$country] = array();
                $obj[$id]["energy"][$country]['expected'] = $d['grid_energy_expected'];
                $obj[$id]["energy"][$country]['country'] = $country;
                $obj[$id]["energy"][$country]['sockets'] = array();
            }

            $obj[$id]["energy"][$country]['sockets'][] = $d['geometry_id'];
        }

        //convert everything to arrays instead of objects
        $robj = array_values($obj);
        foreach ($robj as &$r) {
            $r['energy'] = array_values($r['energy']);
        }

        return $robj;
    }

    /**
     * @apiGroup Cel
     * @throws Exception
     * @api {POST} /cel/SetGeomCapacity Set Geometry Capacity
     * @apiDescription Set the energy capacity of a specific geometry object
     * @apiParam {string} geomCapacityValues Json Encoded string in the format
     *   [{ "id" : GRID_ID, "capacity": CAPACITY_VALUE }]
     * @apiParam {int} capacity capacity of node
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetGeomCapacity(string $geomCapacityValues): void
    {
        $values = json_decode($geomCapacityValues, true);
        foreach ($values as $value) {
            $this->getDatabase()->query(
                "
                UPDATE energy_output SET energy_output_capacity=?, energy_output_lastupdate=UNIX_TIMESTAMP(NOW(6))
                WHERE energy_output_geometry_id=?
                ",
                array($value['capacity'], $value['id'])
            );
        }
    }

    /**
     * @apiGroup Cel
     * @throws Exception
     * @api {POST} /cel/SetGridCapacity Set Grid Capacity
     * @apiDescription Set the energy capacity of a grid per country, uses the server month time
     * @apiParam {string} kpiValues Json encoded string in the format [ { "grid": GRID_ID, "actual":
     *   ACTUAL_ENERGY_VALUE, "country": COUNTRY_ID } ]
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetGridCapacity(string $kpiValues): void
    {
        $gameMonth = $this->getDatabase()->query(
            "SELECT game_currentmonth FROM game WHERE game_id = 1"
        )[0]["game_currentmonth"];

        $values = json_decode($kpiValues, true);
        foreach ($values as $value) {
            $this->getDatabase()->query(
                "
                INSERT INTO energy_kpi (
                    energy_kpi_grid_id, energy_kpi_month, energy_kpi_country_id, energy_kpi_actual,
                    energy_kpi_lastupdate
                ) 
                VALUES (?, ?, ?, ?, UNIX_TIMESTAMP(NOW(6)))
                ON DUPLICATE KEY UPDATE energy_kpi_actual = ?, energy_kpi_lastupdate = UNIX_TIMESTAMP(NOW(6))
                ",
                array(
                    $value['grid'], $gameMonth, $value['country'], $value['actual'], $value['actual']
                )
            );
        }
    }
}
