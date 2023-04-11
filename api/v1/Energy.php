<?php

namespace App\Domain\API\v1;

use Doctrine\DBAL\Query\QueryBuilder;
use Drift\DBAL\Result;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\tpf;
use function App\await;

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
    public function UpdateGridSockets(int $id, array $sockets): ?PromiseInterface
    {
        $deferred = new Deferred();
        $this->getAsyncDatabase()->delete('grid_socket', ['grid_socket_grid_id' => $id])
            ->then(function (/* Result $result */) use ($id, $sockets) {
                $toPromiseFunctions = [];
                foreach ($sockets as $socketId) {
                    $toPromiseFunctions[$socketId] = tpf(function () use ($id, $socketId) {
                        return $this->getAsyncDatabase()->insert('grid_socket', [
                            'grid_socket_grid_id' => $id,
                            'grid_socket_geometry_id' => $socketId
                        ]);
                    });
                }
                return parallel($toPromiseFunctions);
            })
            ->done(
                function (/* array $results */) use ($deferred) {
                    $deferred->resolve(); // we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
    public function UpdateGridSources(int $id, array $sources = array()): ?PromiseInterface
    {
        $deferred = new Deferred();
        $this->getAsyncDatabase()->delete('grid_source', ['grid_source_grid_id' => $id])
            ->then(function (/* Result $result */) use ($id, $sources) {
                $toPromiseFunctions = [];
                foreach ($sources as $sourceId) {
                    $toPromiseFunctions[$sourceId] = tpf(function () use ($id, $sourceId) {
                        return $this->getAsyncDatabase()->insert('grid_source', [
                            'grid_source_grid_id' => $id,
                            'grid_source_geometry_id' => $sourceId
                        ]);
                    });
                }
                return parallel($toPromiseFunctions);
            })
            ->done(
                function (/* array $results */) use ($deferred) {
                    $deferred->resolve(); // we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
    public function UpdateGridEnergy(int $id, array $expected): ?PromiseInterface
    {
        foreach ($expected as $country) {
            $int = (int)filter_var($country['country_id'] ?? null, FILTER_VALIDATE_INT);
            if ($int <= 0) {
                throw new Exception(
                    'Encountered invalid integer country_id value (should be 1+): ' . $country['country_id']
                );
            }
            if (false === filter_var($country['energy_expected'] ?? null, FILTER_VALIDATE_INT)) {
                throw new Exception(
                    'Encountered invalid non integer energy_expected value: ' . $country['energy_expected']
                );
            }
        }

        $deferred = new Deferred();
        $this->getAsyncDatabase()->delete('grid_energy', ['grid_energy_grid_id' => $id])
            ->then(function (/* Result $result */) use ($id, $expected) {
                $toPromiseFunctions = [];
                foreach ($expected as $country) {
                    $toPromiseFunctions[] = tpf(function () use ($id, $country) {
                        return $this->getAsyncDatabase()->insert('grid_energy', [
                            'grid_energy_grid_id' => $id,
                            'grid_energy_country_id' => $country['country_id'],
                            'grid_energy_expected' => $country['energy_expected']
                        ]);
                    });
                }
                return parallel($toPromiseFunctions);
            })
            ->done(
                function (/* array $results */) use ($deferred) {
                    $deferred->resolve(); // we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
        $this->getDatabase()->query(
            "
            UPDATE energy_output SET energy_output_maxcapacity=?, energy_output_lastupdate=UNIX_TIMESTAMP(NOW(6))
            WHERE energy_output_geometry_id=?
            ",
            array($maxcapacity, $id)
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
        $this->getDatabase()->query(
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
    public function DeleteOutput(int $id): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('energy_output')
                ->set('energy_output_active', $qb->createPositionalParameter(0))
                ->set('energy_output_lastupdate', 'UNIX_TIMESTAMP(NOW(4))')
                ->where($qb->expr()->eq('energy_output_geometry_id', $id))
        )
        ->done(
            function (/* Result $result */) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
    public function UpdateGridName(int $id, string $name): ?PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $promise = $this->getAsyncDatabase()->query(
            $qb
                ->update('grid')
                ->set('grid_name', $qb->createPositionalParameter($name))
                ->set('grid_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
                ->where($qb->expr()->eq('grid_id', $qb->createPositionalParameter($id)))
        )
        ->then(function (Result $result) {
            return null; // we do not care about the result
        });
        return $this->isAsync() ? $promise : await($promise);
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
    public function DeleteGrid(int $id): ?PromiseInterface
    {
        $deferred = new Deferred();
        $toPromiseFunctions[] = tpf(function () use ($id) {
            return $this->getAsyncDatabase()->delete('grid_socket', ['grid_socket_grid_id' => $id]);
        });
        $toPromiseFunctions[] = tpf(function () use ($id) {
            return $this->getAsyncDatabase()->delete('grid_source', ['grid_source_grid_id' => $id]);
        });
        $toPromiseFunctions[] = tpf(function () use ($id) {
            return $this->getAsyncDatabase()->delete('grid_energy', ['grid_energy_grid_id' => $id]);
        });
        $toPromiseFunctions[] = tpf(function () use ($id) {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                     ->select('p.plan_id')
                     ->from('plan', 'p')
                     ->innerJoin(
                         'p',
                         'grid',
                         'g',
                         'g.grid_plan_id = p.plan_id AND g.grid_id = ' . $qb->createPositionalParameter($id)
                     )
            )
            ->then(function (Result $result) {
                $planIds = collect($result->fetchAllRows() ?? [])
                    ->keyBy('plan_id')
                    ->map(function ($row) {
                        return $row['plan_id'];
                    })->all();
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $qb
                    ->update('plan', 'p')
                    ->set('p.plan_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
                    ->where($qb->expr()->in('p.plan_id', $planIds));
            });
        });
        parallel($toPromiseFunctions)
            ->then(function (/* array $results */) use ($id) {
                return $this->getAsyncDatabase()->delete('grid', ['grid_id' => $id]);
            })
            ->done(
                function (/* Result $result */) use ($deferred) {
                    $deferred->resolve(); // we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
     * @return int|PromiseInterface
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function AddGrid(
        string $name,
        int $plan,
        bool $distribution_only,
        int $persistent = -1
    ) {/*: int|PromiseInterface // <-- php 8 */
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->insert('grid')
                ->values([
                    'grid_name' => $qb->createPositionalParameter($name),
                    'grid_lastupdate' => $qb->createPositionalParameter(microtime()),
                    'grid_plan_id' => $qb->createPositionalParameter($plan),
                    'grid_distribution_only' => $qb->createPositionalParameter($distribution_only)
                ])
        )
        ->then(function (Result $result) use ($persistent) {
            if (null === $id = $result->getLastInsertedId()) {
                throw new Exception('Could not retrieve last inserted id');
            }
            return $this->getAsyncDatabase()->update('grid', ['grid_id' => $id], [
                'grid_persistent' => $persistent == -1 ? $id : $persistent
            ])
            // todo: do we actually need to wait for the result?
            ->then(function (/* Result $result */) use ($id) {
                 return $id;
            });
        })
        ->done(
            function (int $insertedId) use ($deferred) {
                $deferred->resolve($insertedId);
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
    public function SetDeleted(int $plan, array $delete = array()): ?PromiseInterface
    {
        $deferred = new Deferred();
        $this->getAsyncDatabase()->delete('grid_removed', ['grid_removed_plan_id' => $plan])
            ->then(function (/* Result $result */) use ($plan, $delete) {
                $toPromiseFunctions = [];
                foreach ($delete as $deleteId) {
                    $toPromiseFunctions[] = tpf(function () use ($plan, $deleteId) {
                        return $this->getAsyncDatabase()->insert('grid_removed', [
                            'grid_removed_plan_id' => $plan,
                            'grid_removed_grid_persistent' => $deleteId
                        ]);
                    });
                }
                return parallel($toPromiseFunctions);
            })
            ->done(
                function (/* array $results */) use ($deferred) {
                    $deferred->resolve(); // we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
        $this->getDatabase()->query(
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
        $this->getDatabase()->query("DELETE FROM grid_socket WHERE grid_socket_geometry_id=?", array($geometry));
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
        $this->getDatabase()->query(
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
        $this->getDatabase()->query("DELETE FROM grid_source WHERE grid_source_geometry_id=?", array($geometry));
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
    public function CreateConnection(int $start, int $end, int $cable, string $coords): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->insert('energy_connection')
                ->values([
                    'energy_connection_start_id' => $qb->createPositionalParameter($start),
                    'energy_connection_end_id' => $qb->createPositionalParameter($end),
                    'energy_connection_cable_id' => $qb->createPositionalParameter($cable),
                    'energy_connection_start_coordinates' => $qb->createPositionalParameter($coords),
                    'energy_connection_lastupdate' => 'UNIX_TIMESTAMP(NOW(6))'
                ])
        )
        ->done(
            function (/* Result $result */) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
    public function UpdateConnection(int $start, int $end, int $cable, string $coords): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('energy_connection')
                ->set('energy_connection_start_id', $qb->createPositionalParameter($start))
                ->set('energy_connection_end_id', $qb->createPositionalParameter($end))
                ->set('energy_connection_start_coordinates', $qb->createPositionalParameter($coords))
                ->set('energy_connection_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
                ->where($qb->expr()->eq('energy_connection_cable_id', $qb->createPositionalParameter($cable)))
        )
        ->done(
            function (/* Result $result */) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
    public function DeleteConnection(int $cable): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('energy_connection')
                ->set('energy_connection_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
                ->set('energy_connection_active', '0')
                ->where(
                    $qb->expr()->and(
                        $qb->expr()->eq('energy_connection_cable_id', $qb->createPositionalParameter($cable))
                    )
                    ->with($qb->expr()->eq('energy_connection_active', 1))
                )
        )
        ->done(
            function (/* Result $result */) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DeleteEnergyInformationFromLayer(int $layerId): ?PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $promise = $this->getAsyncDatabase()->query(
            $qb
                ->select('geometry_id')
                ->from('geometry')
                ->where($qb->expr()->eq('geometry_layer_id', $qb->createPositionalParameter($layerId)))
        )
        ->then(function (Result $result) {
            $geometryToDelete = $result->fetchAllRows() ?? [];
            $toPromiseFunctions = [];
            foreach ($geometryToDelete as $geometry) {
                $geometryId = $geometry['geometry_id'];
                $toPromiseFunctions[] = tpf(function () use ($geometryId) {
                     $qb = $this->getAsyncDatabase()->createQueryBuilder();
                     return $this->getAsyncDatabase()->query(
                         $qb
                            ->delete('energy_connection')
                            ->where(
                                $qb->expr()->eq(
                                    'energy_connection_start_id',
                                    $qb->createPositionalParameter($geometryId)
                                )
                            )
                            ->orWhere(
                                $qb->expr()->eq(
                                    'energy_connection_end_id',
                                    $qb->createPositionalParameter($geometryId)
                                )
                            )
                            ->orWhere(
                                $qb->expr()->eq(
                                    'energy_connection_cable_id',
                                    $qb->createPositionalParameter($geometryId)
                                )
                            )
                     );
                });
                $toPromiseFunctions[] = tpf(function () use ($geometryId) {
                     return $this->getAsyncDatabase()->delete(
                         'energy_output',
                         ['energy_output_geometry_id' => $geometryId]
                     );
                });
            }
            return parallel($toPromiseFunctions);
        });

        return $this->isAsync() ? $promise : await($promise);
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
    public function SetOutput(int $id, float $capacity, float $maxcapacity): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->select('energy_output_id')
                ->from('energy_output')
                ->where($qb->expr()->eq('energy_output_geometry_id', $qb->createPositionalParameter($id)))
        )
        ->then(function (Result $result) use ($id, $capacity, $maxcapacity) {
            $rows = $result->fetchAllRows();
            if (empty($rows)) {
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $this->getAsyncDatabase()->query(
                    $qb
                        ->insert('energy_output')
                        ->values([
                            'energy_output_geometry_id' => $qb->createPositionalParameter($id),
                            'energy_output_capacity' => $qb->createPositionalParameter($capacity),
                            'energy_output_maxcapacity' => $qb->createPositionalParameter($maxcapacity),
                            'energy_output_lastupdate' => 'UNIX_TIMESTAMP(NOW(6))'
                        ])
                );
            }
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->update('energy_output')
                    ->set('energy_output_capacity', $qb->createPositionalParameter($capacity))
                    ->set('energy_output_maxcapacity', $qb->createPositionalParameter($maxcapacity))
                    ->set('energy_output_active', '1')
                    ->set('energy_output_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
                    ->where($qb->expr()->eq('energy_output_geometry_id', $qb->createPositionalParameter($id)))
            );
        })
        ->done(
            function (/* Result $result */) use ($deferred) {
                $deferred->resolve(); // we do not care about the result
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
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
        return $this->getDatabase()->query(
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
        $this->getDatabase()->query("TRUNCATE TABLE energy_connection");
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
    public function GetDependentEnergyPlans(int $plan_id): array|PromiseInterface
    {
        $planId = $plan_id;
        $result = array();
        $promise = $this->findDependentEnergyPlans($planId, $result);
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * Internal function which returns a PHP array. Not for usage in the API
     *
     * @throws Exception
     */
    public function findDependentEnergyPlans(int $planId, array &$result): PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
            $qb
                ->select('plan_gametime')
                ->from('plan')
                ->where('plan_id = ' . $qb->createPositionalParameter($planId))
                ->setMaxResults(1)
        )
        ->then(function (Result $queryResult) use ($planId, &$result) {
            $referencePlanData = $queryResult->fetchFirstRow();
            //Plans that are referencing the same persistent grid ids.
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select('p.plan_id')
                    ->from('plan', 'p')
                    ->innerJoin('p', 'grid', 'g', 'p.plan_id = g.grid_plan_id')
                    ->where(
                        $qb->expr()->and(
                            $qb->expr()->or(
                                'p.plan_gametime > ' .
                                    $qb->createPositionalParameter($referencePlanData['plan_gametime']),
                                $qb->expr()->and(
                                    'p.plan_gametime = ' .
                                        $qb->createPositionalParameter($referencePlanData['plan_gametime']),
                                    'p.plan_id > ' . $qb->createPositionalParameter($planId)
                                )
                            ),
                            $qb->expr()->in(
                                'g.grid_persistent',
                                'SELECT grid.grid_persistent FROM grid WHERE grid.grid_plan_id = ' .
                                    $qb->createPositionalParameter($planId)
                            )
                        )
                    )
            )
            ->then(function (Result $queryResult) use (&$result, $planId, $referencePlanData) {
                $planChangingReferencedGrids = $queryResult->fetchAllRows();
                $planIdsDependentOnThisPlan = [];
                foreach ($planChangingReferencedGrids as $plan) {
                    if (!in_array($plan['plan_id'], $planIdsDependentOnThisPlan) &&
                        !in_array($plan['plan_id'], $result)) {
                        $planIdsDependentOnThisPlan[] = $plan['plan_id'];
                    }
                }

                //Plans that are deleting the persistent id
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $this->getAsyncDatabase()->query(
                    $qb
                        ->select('p.plan_id')
                        ->from('plan', 'p')
                        ->innerJoin('p', 'grid_removed', 'gr', 'p.plan_id = gr.grid_removed_plan_id')
                        ->where(
                            $qb->expr()->and(
                                $qb->expr()->or(
                                    'p.plan_gametime > ' .
                                        $qb->createPositionalParameter($referencePlanData['plan_gametime']),
                                    $qb->expr()->and(
                                        'p.plan_gametime = ' .
                                            $qb->createPositionalParameter($referencePlanData['plan_gametime']),
                                        'p.plan_id > ' . $qb->createPositionalParameter($planId)
                                    )
                                ),
                                $qb->expr()->or(
                                    $qb->expr()->in(
                                        'gr.grid_removed_grid_persistent',
                                        'SELECT grid.grid_persistent FROM grid WHERE grid.grid_plan_id = ' .
                                            $qb->createPositionalParameter($planId)
                                    ),
                                    $qb->expr()->in(
                                        'gr.grid_removed_grid_persistent',
                                        '
                                        SELECT grid_removed.grid_removed_grid_persistent
                                        FROM grid_removed
                                        WHERE grid_removed.grid_removed_grid_persistent = ' .
                                            $qb->createPositionalParameter($planId)
                                    )
                                )
                            )
                        )
                )
                ->then(function (Result $queryResult) use (
                    &$result,
                    $planId,
                    $referencePlanData,
                    $planIdsDependentOnThisPlan
                ) {
                    $plansReferencingDeletedGrids = $queryResult->fetchAllRows();
                    foreach ($plansReferencingDeletedGrids as $plan) {
                        if (!in_array($plan['plan_id'], $planIdsDependentOnThisPlan) &&
                            !in_array($plan['plan_id'], $result)) {
                            $planIdsDependentOnThisPlan[] = $plan['plan_id'];
                        }
                    }

                    // temp. function to re-use part of query where
                    $fnGeneratePlanLayerWherePart = function (
                        QueryBuilder $qb,
                        string $planLayerTableName
                    ) use (
                        $planId,
                        $referencePlanData
                    ) {
                        return $qb->expr()->and(
                            $planLayerTableName . '.plan_layer_plan_id = ' .
                            $qb->createPositionalParameter($planId),
                            'plan_connection.plan_id != ' . $qb->createPositionalParameter($planId),
                            $qb->expr()->or(
                                'plan_connection.plan_gametime > ' .
                                    $qb->createPositionalParameter($referencePlanData['plan_gametime']),
                                $qb->expr()->and(
                                    'plan_connection.plan_gametime = ' .
                                        $qb->createPositionalParameter($referencePlanData['plan_gametime']),
                                    'plan_connection.plan_id > ' . $qb->createPositionalParameter($planId)
                                )
                            )
                        );
                    };

                    //Plans that have connections to any geometry in the current plan.
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    return $this->getAsyncDatabase()->query(
                        $qb
                            ->select('plan_connection.plan_id')
                            ->from('energy_connection', 'ec')
                            ->innerJoin(
                                'ec',
                                'geometry',
                                'geometry_start',
                                'ec.energy_connection_start_id = geometry_start.geometry_id'
                            )
                            ->innerJoin(
                                'geometry_start',
                                'plan_layer',
                                'plan_layer_start',
                                'geometry_start.geometry_layer_id = plan_layer_start.plan_layer_layer_id'
                            )
                            ->innerJoin(
                                'ec',
                                'geometry',
                                'geometry_end',
                                'ec.energy_connection_end_id = geometry_end.geometry_id'
                            )
                            ->innerJoin(
                                'geometry_end',
                                'plan_layer',
                                'plan_layer_end',
                                'geometry_end.geometry_layer_id = plan_layer_end.plan_layer_layer_id'
                            )
                            ->innerJoin(
                                'ec',
                                'geometry',
                                'geometry_connection',
                                'ec.energy_connection_cable_id = geometry_connection.geometry_id'
                            )
                            ->innerJoin(
                                'geometry_connection',
                                'plan_layer',
                                'plan_layer_connection',
                                'geometry_connection.geometry_layer_id = plan_layer_connection.plan_layer_layer_id'
                            )
                            ->innerJoin(
                                'plan_layer_connection',
                                'plan',
                                'plan_connection',
                                'plan_layer_connection.plan_layer_plan_id = plan_connection.plan_id'
                            )
                            ->where($qb->expr()->and(
                                'geometry_start.geometry_active = 1',
                                'geometry_end.geometry_active = 1',
                                'geometry_connection.geometry_active = 1',
                                $qb->expr()->or(
                                    $fnGeneratePlanLayerWherePart($qb, 'plan_layer_start'),
                                    $fnGeneratePlanLayerWherePart($qb, 'plan_layer_end')
                                )
                            ))
                    )
                    ->then(function (Result $queryResult) use (
                        &$result,
                        $planIdsDependentOnThisPlan
                    ) {
                        $plansWithCablesReferencingGeometry = $queryResult->fetchAllRows();
                        foreach ($plansWithCablesReferencingGeometry as $plan) {
                            if (!in_array($plan['plan_id'], $planIdsDependentOnThisPlan) &&
                                !in_array($plan['plan_id'], $result)) {
                                $planIdsDependentOnThisPlan[] = $plan['plan_id'];
                            }
                        }
                        $toPromiseFunctions = [];
                        foreach ($planIdsDependentOnThisPlan as $erroredPlanId) {
                            if (!in_array($erroredPlanId, $result)) {
                                $result[] = $erroredPlanId;
                                $toPromiseFunctions[] = tpf(function () use ($erroredPlanId, &$result) {
                                    return $this->findDependentEnergyPlans($erroredPlanId, $result);
                                });
                            }
                        }
                        return parallel($toPromiseFunctions);
                    });
                });
            });
        })
        ->then(function (/* array $results */) use (&$result) {
            return $result;
        });
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
    public function GetOverlappingEnergyPlans(int $plan_id): array|PromiseInterface
    {
        $result = array();
        if (null !== $promise = $this->findOverlappingEnergyPlans($plan_id, $result)) {
            return $promise;
        }
        return $result;
    }

    /**
     * Check future plans for any references to grids that we delete in the current plan.
     *
     * @throws Exception
     */
    public function findOverlappingEnergyPlans(int $planId, array &$result): ?PromiseInterface
    {
        // todo: convert to createQueryBuilder.
        $promise = $this->getAsyncDatabase()->queryBySQL(
            '
            SELECT COALESCE(
                grid_removed.grid_removed_grid_persistent, grid.grid_persistent
            ) AS grid_persistent, plan.plan_gametime 
            FROM plan
            LEFT OUTER JOIN grid_removed ON grid_removed.grid_removed_plan_id = plan.plan_id
            LEFT OUTER JOIN grid ON grid.grid_plan_id = plan.plan_id
            WHERE grid_removed.grid_removed_plan_id = ? OR grid.grid_plan_id = ?
            ',
            [$planId, $planId]
        )
        ->then(function (Result $qResult) use ($planId, &$result) {
            $removedGridIds = $qResult->fetchAllRows() ?: [];
            $toPromiseFunctions = [];
            foreach ($removedGridIds as $removedGridId) {
                $toPromiseFunctions[] = tpf(function () use ($removedGridId, $planId, &$result) {
                    return $this->getAsyncDatabase()->queryBySQL(
                        '
                        SELECT grid_plan_id as plan_id 
                        FROM grid INNER JOIN plan ON grid.grid_plan_id = plan.plan_id
                        WHERE grid_persistent = ? AND (
                            plan.plan_gametime > ? OR (
                                plan.plan_gametime = ? AND plan.plan_id > ?
                            )
                        )
                        ',
                        [
                            $removedGridId['grid_persistent'],
                            $removedGridId['plan_gametime'],
                            $removedGridId['plan_gametime'],
                            $planId
                        ]
                    )
                    ->then(function (Result $qResult) use (&$result) {
                        $futureReferencedGrids = $qResult->fetchAllRows() ?: [];
                        foreach ($futureReferencedGrids as $futureGrid) {
                            $result[] = $futureGrid['plan_id'];
                        }
                    });
                });
                $toPromiseFunctions[] = tpf(function () use ($removedGridId, $planId, &$result) {
                    return $this->getAsyncDatabase()->queryBySQL(
                        '
                        SELECT grid_removed_plan_id as plan_id 
                        FROM grid_removed
                        INNER JOIN plan on grid_removed.grid_removed_plan_id = plan.plan_id
                        WHERE grid_removed_grid_persistent = ? AND (
                            plan.plan_gametime > ? OR (plan.plan_gametime = ? AND plan.plan_id > ?))
                        ',
                        [
                            $removedGridId['grid_persistent'],
                            $removedGridId['plan_gametime'],
                            $removedGridId['plan_gametime'],
                            $planId
                        ]
                    )
                    ->then(function (Result $qResult) use (&$result) {
                        $futureDeletedGrids = $qResult->fetchAllRows() ?: [];
                        foreach ($futureDeletedGrids as $futureDeletedGrid) {
                            $result[] = $futureDeletedGrid['plan_id'];
                        }
                    });
                });
            }
            return parallel($toPromiseFunctions)
                ->then(function (/*array $qResults*/) use (&$result) {
                    $result = array_unique($result);
                });
        });
        return $this->isAsync() ? $promise : await($promise);
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
        $isOverlappingPlan = $this->findPreviousOverlappingPlans($plan_id);

        return $isOverlappingPlan ? 1 : 0;
    }

    /**
     * Check past plans in influencing states for grids in the current plan that are deleted.
     *
     * @throws Exception
     */
    private function findPreviousOverlappingPlans(int $planId): bool
    {
        $planData = $this->getDatabase()->query(
            "SELECT plan_gametime FROM plan WHERE plan_id = ?",
            array($planId)
        );
        // @todo: Does not actually check if plans are in influencing state
        $result = $this->getDatabase()->query(
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
            
        $result = $this->getDatabase()->query(
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

        $grid_sources = $this->getDatabase()->query(
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

        $grid_sockets = $this->getDatabase()->query(
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
