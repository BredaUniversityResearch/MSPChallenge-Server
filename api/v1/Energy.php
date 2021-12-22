<?php

namespace App\Domain\API\v1;

use Exception;

class Energy extends Base
{
    private const ALLOWED = array(
        "Start",
        "CreateConnection",
        "UpdateConnection",
        "DeleteConnection",
        "GetConnections",
        "Clear",
        "SetOutput",
        "UpdateMaxCapacity",
        "GetUsedCapacity",
        "DeleteOutput",
        "UpdateGridName",
        "DeleteGrid",
        "AddGrid",
        "UpdateGridEnergy",
        "AddSocket",
        "DeleteSocket",
        "SetDeleted",
        "UpdateGridSockets",
        "UpdateGridSources",
        "GetDependentEnergyPlans",
        "GetOverlappingEnergyPlans",
        "GetPreviousOverlappingPlans",
        "VerifyEnergyCapacity",
        "VerifyEnergyGrid"
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Start()
    {
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/UpdateGridSockets Update Grid Sockets
     * @apiParam {int} id grid id
     * @apiParam {array(int)} sockets Array of geometry ids for new sockest.
     * @apiDescription When called, does the following:
     *   <br/>1. Removes all grid_socket entries with the given grid_socket_grid_id.
     *   <br/>2. Adds new entries for all geomID combinations in "grid_socket", with grid_socket_grid_id set to the
     *   given value.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateGridSockets(int $id, array $sockets): void
    {
        Database::GetInstance()->query("DELETE FROM grid_socket WHERE grid_socket_grid_id=?", array($id));

        foreach ($sockets as $str) {
            Database::GetInstance()->query(
                "INSERT INTO grid_socket (grid_socket_grid_id, grid_socket_geometry_id) VALUES (?, ?)",
                array($id, $str)
            );
        }
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/UpdateGridSources Update Grid Sources
     * @apiParam {int} id grid id
     * @apiParam {int} sources a json array of geometry IDs Example: [1,2,3,4]
     * @apiDescription When called, does the following:
     *   <br/>1. Removes all grid_source entries with the given grid_source_grid_id.
     *   <br/>2. Adds new entries for all country:geomID combinations in "grid_source", with grid_source_grid_id set to
     *   the given value.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateGridSources(int $id, array $sources = array()): void
    {
        Database::GetInstance()->query("DELETE FROM grid_source WHERE grid_source_grid_id=?", array($id));
        //throw new Exception("Sources looks like this now: ".var_export($sources, true));
        foreach ($sources as $str) {
            Database::GetInstance()->query(
                "INSERT INTO grid_source (grid_source_grid_id, grid_source_geometry_id) VALUES (?, ?)",
                array($id, $str)
            );
        }
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/UpdateGridEnergy Update Grid Energy
     * @apiParam {int} id grid id
     * @apiParam {array(Object)} expected Objects contain country_id and energy_expected values.
     *   E.g. [{"country_id": 3, "energy_expected": 1300}]
     * @apiDescription Adds new entries to grid_energy and deleted all old grid_energy entries for the given grid
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateGridEnergy(int $id, array $expected): void
    {
        Database::GetInstance()->query("DELETE FROM grid_energy WHERE grid_energy_grid_id=?", array($id));

        foreach ($expected as $country) {
            Database::GetInstance()->query(
                "
                INSERT INTO grid_energy (grid_energy_grid_id, grid_energy_country_id, grid_energy_expected)
                VALUES (?, ?, ?)
                ",
                array($id, $country["country_id"], $country["energy_expected"])
            );
        }
    }



    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/UpdateMaxCapacity Update Max Capacity
     * @apiParam {int} id geometry id
     * @apiParam {int} maxcapacity maximum capacity
     * @apiDescription Update the maximum capacity of a geometry object in energy_output
     * @noinspection PhpUnused
     * @noinspection SpellCheckingInspection
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateMaxCapacity(int $id, int $maxcapacity): void
    {
        Database::GetInstance()->query(
            "
            UPDATE energy_output SET energy_output_maxcapacity=?, energy_output_lastupdate=?
            WHERE energy_output_geometry_id=?
            ",
            array($maxcapacity, microtime(true), $id)
        );
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/GetUsedCapacity Get Used Capacity
     * @apiParam {int} id geometry id
     * @apiDescription Get the used capacity of a geometry object in energy_output
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetUsedCapacity(int $id): void
    {
        Database::GetInstance()->query(
            "SELECT energy_output_capacity as capacity FROM energy_output WHERE energy_output_geometry_id=?",
            array($id)
        );
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/DeleteOutput Delete Output
     * @apiParam {int} id geometry id
     * @apiDescription Delete the energy_output of a geometry object
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteOutput(int $id): void
    {
        Database::GetInstance()->query(
            "
            UPDATE energy_output SET energy_output_active=?, energy_output_lastupdate=?
            WHERE energy_output_geometry_id=?
            ",
            array(0, microtime(true), $id)
        );
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/UpdateGridName Update Grid Name
     * @apiParam {int} id grid id
     * @apiParam {string} name grid name
     * @apiDescription Change the name of a grid
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateGridName(int $id, string $name): void
    {
        Database::GetInstance()->query(
            "UPDATE grid SET grid_name=?, grid_lastupdate=? WHERE grid_id=?",
            array($name, microtime(true), $id)
        );
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/DeleteGrid Delete Grid
     * @apiParam {int} id grid id
     * @apiDescription Delete a grid and its sockets, sources and energy by the grid id
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteGrid(int $id): void
    {
        Database::GetInstance()->query("DELETE FROM grid_socket WHERE grid_socket_grid_id=?", array($id));
        Database::GetInstance()->query("DELETE FROM grid_source WHERE grid_source_grid_id=?", array($id));
        Database::GetInstance()->query("DELETE FROM grid_energy WHERE grid_energy_grid_id=?", array($id));
            
        Database::GetInstance()->query(
            "
            UPDATE plan INNER JOIN grid ON grid.grid_plan_id = plan.plan_id SET plan.plan_lastupdate = ?
            WHERE grid.grid_id = ?
            ",
            array(microtime(true), $id)
        );
        Database::GetInstance()->query("DELETE FROM grid WHERE grid_id=?", array($id));
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/AddGrid Add Grid
     * @apiParam {string} name grid name
     * @apiParam {int} plan plan id
     * @apiParam {int} distribution_only ...
     * @apiParam {int} persistent (optional) persistent id, defaults to the newly created id
     * @apiDescription Add a new grid
     * @apiSuccess {int} success grid id
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function AddGrid(string $name, int $plan, bool $distribution_only, int $persistent = -1): string
    {
        $id = Database::GetInstance()->query(
            "INSERT INTO grid (grid_name, grid_lastupdate, grid_plan_id, grid_distribution_only) VALUES (?, ?, ?, ?)",
            array($name, microtime(true), $plan, $distribution_only),
            true
        );
        Database::GetInstance()->query(
            "UPDATE grid SET grid_persistent=? WHERE grid_id=?",
            array(($persistent == -1) ? $id : $persistent, $id)
        );
        return $id;
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/SetDeleted Set Deleted
     * @apiParam {int} plan plan id
     * @apiParam {array} delete Json array of persistent ids of grids to be removed
     * @apiDescription Set the grids to be deleted in this plan. Will first remove the previously deleted grids for
     *   the plan and then add the new ones. Note that there is no verification if the added values are actually
     *   correct.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetDeleted(int $plan, array $delete = array())
    {
        Database::GetInstance()->query("DELETE FROM grid_removed WHERE grid_removed_plan_id=?", array($plan));

        foreach ($delete as $remove) {
            Database::GetInstance()->query(
                "INSERT INTO grid_removed (grid_removed_plan_id, grid_removed_grid_persistent) VALUES (?, ?)",
                array($plan, $remove)
            );
        }
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/AddSocket Add Socket
     * @apiParam {int} grid grid id
     * @apiParam {int} geometry geometry id
     * @apiDescription Add a new socket for a single country for a certain grid
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function AddSocket(int $grid, int $geometry): void
    {
        Database::GetInstance()->query(
            "INSERT INTO grid_socket (grid_socket_grid_id, grid_socket_geometry_id) VALUES (?, ?)",
            array($grid, $geometry)
        );
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/DeleteSocket Delete Socket
     * @apiParam {int} geometry geometry id
     * @apiDescription Delete the sockets of a geometry object
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteSocket(int $geometry): void
    {
        Database::GetInstance()->query("DELETE FROM grid_socket WHERE grid_socket_geometry_id=?", array($geometry));
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/AddSource Add Source
     * @apiParam {int} grid grid id
     * @apiParam {int} geometry geometry id
     * @apiDescription Add a new socket for a single country for a certain grid
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function AddSource(int $grid, int $geometry): void
    {
        Database::GetInstance()->query(
            "INSERT INTO grid_source (grid_source_grid_id, grid_source_geometry_id) VALUES (?, ?)",
            array($grid, $geometry)
        );
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/DeleteSource Delete Source
     * @apiParam {int} geometry geometry id
     * @apiDescription Delete the sources of a geometry object
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteSource(int $geometry): void
    {
        Database::GetInstance()->query("DELETE FROM grid_source WHERE grid_source_geometry_id=?", array($geometry));
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/CreateConnection Create Connection
     * @apiParam {int} start ID of the start geometry
     * @apiParam {int} end ID of the end geometry
     * @apiParam {int} cable ID of the cable geometry
     * @apiParam {string} coords coordinates of the starting point, saved as: [123.456, 999.123]
     * @apiDescription Create a new connection between 2 points
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CreateConnection(int $start, int $end, int $cable, string $coords): void
    {
        Database::GetInstance()->query(
            "
            INSERT INTO energy_connection (
                energy_connection_start_id, energy_connection_end_id, energy_connection_cable_id,
                energy_connection_start_coordinates, energy_connection_lastupdate
            ) VALUES (?, ?, ?, ?, ?)
            ",
            array($start, $end, $cable, $coords, microtime(true))
        );
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/UpdateConnection Update Connection
     * @apiParam {int} start ID of the start geometry
     * @apiParam {int} end ID of the end geometry
     * @apiParam {string} coords coordinates of the starting point, saved as: [123.456, 999.123]
     * @apiParam {int} cable ID of the cable geometry of which to update
     * @apiDescription Update cable connection between 2 points
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UpdateConnection(int $start, int $end, int $cable, string $coords): void
    {
        Database::GetInstance()->query(
            "UPDATE energy_connection SET energy_connection_start_id = ?, 
																		 energy_connection_end_id = ?, 
																		 energy_connection_start_coordinates = ?, 
																		 energy_connection_lastupdate = ?
																		WHERE energy_connection_cable_id = ?;",
            array($start, $end, $coords, microtime(true), $cable)
        );
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/DeleteConnection Delete Connection
     * @apiParam {int} cable ID of the cable geometry
     * @apiDescription Deletes a connection
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteConnection(int $cable): void
    {
        Database::GetInstance()->query(
            "
            UPDATE energy_connection SET energy_connection_lastupdate=?, energy_connection_active=?
            WHERE energy_connection_cable_id=? AND energy_connection_active=?
            ",
            array(microtime(true), 0, $cable, 1)
        );
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteEnergyInformationFromLayer(int $layerId): void
    {
        $geometryToDelete = Database::GetInstance()->query(
            "SELECT geometry_id FROM geometry WHERE geometry_layer_id = ?",
            array($layerId)
        );
        foreach ($geometryToDelete as $geometry) {
            Database::GetInstance()->query(
                "
                DELETE FROM energy_connection
                WHERE energy_connection_start_id = :geometryId OR energy_connection_end_id = :geometryId OR
                      energy_connection_cable_id = :geometryId
                ",
                array("geometryId" => $geometry['geometry_id'])
            );
            Database::GetInstance()->query(
                "DELETE FROM energy_output WHERE energy_output_geometry_id = ?",
                array($geometry['geometry_id'])
            );
        }
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/SetOutput Set Output
     * @apiParam {int} id id of geometry
     * @apiParam {float} capacity current node capacity
     * @apiParam {float} maxcapacity maximum capacity of node
     * @apiDescription Creates or updates the output of an element
     * @noinspection PhpUnused
     * @noinspection SpellCheckingInspection
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetOutput(int $id, float $capacity, float $maxcapacity): void
    {
        $data = Database::GetInstance()->query(
            "SELECT energy_output_id FROM energy_output WHERE energy_output_geometry_id=?",
            array($id)
        );

        if (empty($data)) {
            Database::GetInstance()->query(
                "
                INSERT INTO energy_output (
                    energy_output_geometry_id, energy_output_capacity, energy_output_maxcapacity,
                    energy_output_lastupdate
                ) VALUES (?, ?, ?, ?)
                ",
                array($id, $capacity, $maxcapacity, microtime(true))
            );
        } else {
            Database::GetInstance()->query(
                "UPDATE energy_output SET 
					energy_output_capacity=?,
					energy_output_maxcapacity=?,
					energy_output_active=?,
					energy_output_lastupdate=?
					WHERE energy_output_geometry_id=?",
                array($capacity, $maxcapacity, 1, microtime(true), $id)
            );
        }
    }

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
        // @todo: This is part of CEL
        return Database::GetInstance()->query(
            "SELECT 
					energy_connection_start_id as start,
					energy_connection_end_id as end,
					energy_connection_cable_id as cable,
					energy_connection_start_coordinates as coords
				FROM energy_connection
				WHERE energy_connection_active=?",
            array(1)
        );
    }

    /**
     * internal
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Clear(): void
    {
        Database::GetInstance()->query("TRUNCATE TABLE energy_connection");
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Latest(float $time): array
    {
        $data = array();
        $data['connections'] = Database::GetInstance()->query("SELECT 
				energy_connection_start_id as start,
				energy_connection_end_id as end,
				energy_connection_cable_id as cable,
				energy_connection_start_coordinates as coords,
				energy_connection_active as active
			FROM energy_connection WHERE energy_connection_lastupdate > ?", array($time));

        $data["output"] = Database::GetInstance()->query("SELECT 
				energy_output_geometry_id as id, 
				energy_output_capacity as capacity,
				energy_output_maxcapacity as maxcapacity, 
				energy_output_active as active
				FROM energy_output 
				WHERE energy_output_lastupdate > ?", array($time));

        return $data;
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/GetDependentEnergyPlans Get Dependent Energy Plans
     * @apiDescription Get all the plan ids that are dependent on this plan
     * @apiParam {int} plan_id Id of the plan that you want to find the dependent energy plans of.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetDependentEnergyPlans(int $plan_id): array
    {
        $planId = $plan_id;
        $result = array();
        $this->FindDependentEnergyPlans($planId, $result);
        return $result;
    }

    /**
     * Internal function which returns a PHP array. Not for usage in the API
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function FindDependentEnergyPlans(int $planId, array &$result): void
    {
        $planIdsDependentOnThisPlan = array();

        $referencePlanData = Database::GetInstance()->query(
            "SELECT plan_gametime FROM plan WHERE plan_id = ? ",
            array($planId)
        )[0];
            
        //Plans that are referencing the same persisten grid ids.
        $planChangingReferencedGrids = Database::GetInstance()->query(
            "
            SELECT plan.plan_id 
            FROM plan
            INNER JOIN grid ON plan.plan_id = grid.grid_plan_id
            WHERE (
                plan.plan_gametime > :planGameTime OR (
                    plan.plan_gametime = :planGameTime AND plan.plan_id > :planId
                )
            ) AND grid.grid_persistent IN (
              SELECT grid.grid_persistent FROM grid WHERE grid.grid_plan_id = :planId
            )
			",
            array(":planGameTime" => $referencePlanData['plan_gametime'], ":planId" => $planId)
        );
        foreach ($planChangingReferencedGrids as $plan) {
            if (!in_array($plan['plan_id'], $planIdsDependentOnThisPlan) && !in_array($plan['plan_id'], $result)) {
                $planIdsDependentOnThisPlan[] = $plan['plan_id'];
            }
        }

        //Plans that are deleting the persistent Id
        $plansReferencingDeletedGrids = Database::GetInstance()->query(
            "
            SELECT plan.plan_id 
            FROM plan
            INNER JOIN grid_removed ON plan.plan_id = grid_removed.grid_removed_plan_id
            WHERE (
                plan.plan_gametime > :planGameTime OR (
                    plan.plan_gametime = :planGameTime AND plan.plan_id > :planId)
                ) AND (
                    grid_removed.grid_removed_grid_persistent IN (
                        SELECT grid.grid_persistent FROM grid WHERE grid.grid_plan_id = :planId
                    ) OR grid_removed.grid_removed_grid_persistent IN (
                        SELECT grid_removed.grid_removed_grid_persistent
                        FROM grid_removed
                        WHERE grid_removed.grid_removed_grid_persistent = :planId)
                    )
			",
            array(":planGameTime" => $referencePlanData['plan_gametime'], ":planId" => $planId)
        );
        foreach ($plansReferencingDeletedGrids as $plan) {
            if (!in_array($plan['plan_id'], $planIdsDependentOnThisPlan) && !in_array($plan['plan_id'], $result)) {
                $planIdsDependentOnThisPlan[] = $plan['plan_id'];
            }
        }

        //Plans that have connections to any geometry in the current plan.
        $plansWithCablesReferencingGeometry = Database::GetInstance()->query(
            "
            SELECT plan_connection.plan_id
			FROM energy_connection 
				INNER JOIN geometry geometry_start
				    ON energy_connection.energy_connection_start_id = geometry_start.geometry_id
				INNER JOIN plan_layer plan_layer_start
				    ON geometry_start.geometry_layer_id = plan_layer_start.plan_layer_layer_id
				INNER JOIN geometry geometry_end
				    ON energy_connection.energy_connection_end_id = geometry_end.geometry_id
				INNER JOIN plan_layer plan_layer_end
				    ON geometry_end.geometry_layer_id = plan_layer_end.plan_layer_layer_id
				INNER JOIN geometry geometry_connection
				    ON energy_connection.energy_connection_cable_id = geometry_connection.geometry_id
				INNER JOIN plan_layer plan_layer_connection
				    ON geometry_connection.geometry_layer_id = plan_layer_connection.plan_layer_layer_id
				INNER JOIN plan plan_connection ON plan_layer_connection.plan_layer_plan_id = plan_connection.plan_id
			WHERE (
			    geometry_start.geometry_active = 1 AND geometry_end.geometry_active = 1 AND
			    geometry_connection.geometry_active = 1
			) AND (
			    (
			        plan_layer_start.plan_layer_plan_id = :planId AND plan_connection.plan_id != :planId AND (
			            plan_connection.plan_gametime > :planGameTime OR (
			                plan_connection.plan_gametime = :planGameTime AND plan_connection.plan_id > :planId
			            )
			        )
			    ) OR (
			        plan_layer_end.plan_layer_plan_id = :planId AND plan_connection.plan_id != :planId AND (
			            plan_connection.plan_gametime > :planGameTime OR (
			                plan_connection.plan_gametime = :planGameTime AND plan_connection.plan_id > :planId
			            )
			        )
			    )
			)
			",
            array(":planId" => $planId, ":planGameTime" => $referencePlanData['plan_gametime'])
        );
        foreach ($plansWithCablesReferencingGeometry as $plan) {
            if (!in_array($plan['plan_id'], $planIdsDependentOnThisPlan) && !in_array($plan['plan_id'], $result)) {
                $planIdsDependentOnThisPlan[] = $plan['plan_id'];
            }
        }

        foreach ($planIdsDependentOnThisPlan as $erroredPlanId) {
            if (!in_array($erroredPlanId, $result)) {
                array_push($result, $erroredPlanId);
                $this->FindDependentEnergyPlans($erroredPlanId, $result);
            }
        }
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/GetOverlappingEnergyPlans Get Overlapping Energy Plans
     * @apiDescription Get all the plan ids that are overlapping with this plan. Meaning they are referencing
     *   deleted grids in the current plan.
     * @apiParam {int} plan_id Id of the plan that you want to find the overlapping energy plans of.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetOverlappingEnergyPlans(int $plan_id): array
    {
        $result = array();
        $this->FindOverlappingEnergyPlans($plan_id, $result);
        return $result;
    }

    /**
     * Check future plans for any references to grids that we delete in the current plan.
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function FindOverlappingEnergyPlans(int $planId, array &$result): void
    {
        $removedGridIds = Database::GetInstance()->query(
            "
            SELECT COALESCE(
                grid_removed.grid_removed_grid_persistent, grid.grid_persistent
            ) AS grid_persistent, plan.plan_gametime 
            FROM plan
            LEFT OUTER JOIN grid_removed ON grid_removed.grid_removed_plan_id = plan.plan_id
            LEFT OUTER JOIN grid ON grid.grid_plan_id = plan.plan_id
            WHERE grid_removed.grid_removed_plan_id = ? OR grid.grid_plan_id = ?
            ",
            array($planId, $planId)
        );
            
        foreach ($removedGridIds as $removedGridId) {
            $futureReferencedGrids = Database::GetInstance()->query(
                "
                SELECT grid_plan_id as plan_id 
                FROM grid INNER JOIN plan ON grid.grid_plan_id = plan.plan_id
                WHERE grid_persistent = :gridPersistent AND (
                    plan.plan_gametime > :gameTime OR (
                        plan.plan_gametime = :gameTime AND plan.plan_id > :planId
                    )
                )
                ",
                array(
                    ":gridPersistent" => $removedGridId['grid_persistent'],
                    ":gameTime" => $removedGridId['plan_gametime'], ":planId" => $planId
                )
            );
                    
            foreach ($futureReferencedGrids as $futureGrid) {
                $result[] = $futureGrid['plan_id'];
            }

            $futureDeletedGrids = Database::GetInstance()->query(
                "
                SELECT grid_removed_plan_id as plan_id 
                FROM grid_removed
                INNER JOIN plan on grid_removed.grid_removed_plan_id = plan.plan_id
                WHERE grid_removed_grid_persistent = :gridPersistent AND (
                    plan.plan_gametime > :gameTime OR (plan.plan_gametime = :gameTime AND plan.plan_id > :planId))
                ",
                array(
                    ":gridPersistent" => $removedGridId['grid_persistent'],
                    ":gameTime" => $removedGridId['plan_gametime'], ":planId" => $planId
                )
            );
                
            foreach ($futureDeletedGrids as $futureDeletedGrid) {
                $result[] = $futureDeletedGrid['plan_id'];
            }
        }

        $result = array_unique($result);
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/GetPreviousOverlappingPlans Get Previous Overlapping Energy Plans
     * @apiDescription Returns whether or not there are overlapping plans in the past that delete grids for the plan
     *   that we are querying.
     * @apiParam {int} plan_id Id of the plan that you want to find the overlapping energy plans of.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetPreviousOverlappingPlans(int $plan_id): int
    {
        $isOverlappingPlan = $this->FindPreviousOverlappingPlans($plan_id);

        return $isOverlappingPlan ? 1 : 0;
    }

    /**
     * Check past plans in influencing states for grids in the current plan that are deleted.
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function FindPreviousOverlappingPlans(int $planId): bool
    {
        $planData = Database::GetInstance()->query(
            "SELECT plan_gametime FROM plan WHERE plan_id = ?",
            array($planId)
        );
        // @todo: Does not actually check if plans are in influencing state
        $result = Database::GetInstance()->query(
            "
            SELECT grid_removed_grid_persistent 
			FROM grid_removed 
            INNER JOIN plan ON grid_removed_plan_id = plan.plan_id
			WHERE plan.plan_gametime < :planGameTime AND (
			    plan.plan_state = 'APPROVED' OR plan.plan_state = 'IMPLEMENTED'
			) AND (
			    grid_removed.grid_removed_grid_persistent IN (
			        SELECT grid.grid_persistent FROM grid WHERE grid.grid_active = 1 AND grid.grid_plan_id = :planId
                ) OR grid_removed.grid_removed_grid_persistent IN (
                    SELECT grid_removed.grid_removed_grid_persistent
                    FROM grid_removed
                    WHERE grid_removed.grid_removed_plan_id = :planId
                )
            )
            ",
            array("planGameTime" => $planData[0]['plan_gametime'], "planId" => $planId)
        );
        return (count($result) > 0);
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/VerifyEnergyCapacity Verify Energy Capacity
     * @apiDescription Returns as an array of the supplied geometry ids were *not* found in the energy_output database
     *   table.
     * @apiParam {string} ids JSON array of integers defining geometry ids to check (e.g. [9554,9562,9563]).
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function VerifyEnergyCapacity(string $ids): ?array
    {
        $geoIds = json_decode($ids, true);
        if (empty($geoIds)) {
            return null;
        }

        $geoIds = array_map('intval', $geoIds);
        $whereClause = implode("','", $geoIds);
            
        $result = Database::GetInstance()->query(
            "
            SELECT energy_output_geometry_id FROM energy_output WHERE energy_output_geometry_id IN (".$whereClause.")
            GROUP BY energy_output_geometry_id
            "
        );
            
        if (count($result) == 0) {
            return null;
        }
        foreach ($result as $returnedgeoIds) {
            if (in_array($returnedgeoIds["energy_output_geometry_id"], $geoIds)) {
                $key = array_search($returnedgeoIds["energy_output_geometry_id"], $geoIds);
                unset($geoIds[$key]);
            }
        }
        if (empty($geoIds)) {
            return null;
        }
        return $geoIds;
    }

    /**
     * @apiGroup Energy
     * @throws Exception
     * @api {POST} /energy/VerifyEnergyGrid Verify Energy Grid
     * @apiDescription Returns a array with client_missing_source_ids, client_extra_source_ids,
     *   client_missing_socket_ids, client_extra_socket_ids, each a comma-separated list of ids
     * @apiParam {int} grid_id grid id of the grid to verify
     * @apiParam {string} source_ids Json array of the grid's source geometry ids on the client
     * @apiParam {string} socket_ids Json array of the grid's sockets geometry ids on the client
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function VerifyEnergyGrid(int $grid_id, string $source_ids, string $socket_ids): ?array
    {
        if (empty($grid_id)) {
            return null;
        }

        $source_ids = json_decode($source_ids, true);
        $socket_ids = json_decode($socket_ids, true);

        $clientMissingSourceIDs = array();
        $clientMissingSocketIDs = array();

        $grid_sources = Database::GetInstance()->query(
            "SELECT * FROM grid_source WHERE grid_source_grid_id = ?",
            array($grid_id)
        );
        foreach ($grid_sources as $grid_source) {
            if (!in_array($grid_source["grid_source_geometry_id"], $source_ids)) {
                $clientMissingSourceIDs[] = $grid_source["grid_source_geometry_id"];
            } else {
                $key = array_search($grid_source["grid_source_geometry_id"], $source_ids);
                unset($source_ids[$key]);
            }
        }
        $clientExtraSourceIDs = $source_ids;

        $grid_sockets = Database::GetInstance()->query(
            "SELECT * FROM grid_socket WHERE grid_socket_grid_id = ?",
            array($grid_id)
        );
        foreach ($grid_sockets as $grid_socket) {
            if (!in_array($grid_socket["grid_socket_geometry_id"], $socket_ids)) {
                $clientMissingSocketIDs[] = $grid_socket["grid_socket_geometry_id"];
            } else {
                $key = array_search($grid_socket["grid_socket_geometry_id"], $socket_ids);
                unset($socket_ids[$key]);
            }
        }
        $clientExtraSocketIDs = $socket_ids;

        return array(
            'client_missing_source_ids' => $clientMissingSourceIDs,
            'client_extra_source_ids' => $clientExtraSourceIDs,
            'client_missing_socket_ids' => $clientMissingSocketIDs,
            'client_extra_socket_ids' => $clientExtraSocketIDs
        );
    }
}
