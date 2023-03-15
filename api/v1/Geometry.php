<?php

namespace App\Domain\API\v1;

use Drift\DBAL\Result;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\tpf;
use function App\await;

class Geometry extends Base
{
    private const ALLOWED = array(
        "Post",
        "PostSubtractive",
        "Update",
        "Data",
        "Delete",
        "MarkForDelete",
        "UnmarkForDelete"
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {post} /geometry/post Post
     * @apiDescription Create a new geometry entry in a plan
     * @apiParam {int} layer id of layer to post in
     * @apiParam {string} geometry string of geometry to post
     * @apiParam {int} plan id of the plan
     * @apiParam {string} FID (optional) FID of geometry
     * @apiParam {int} persistent (optional) persistent ID of geometry
     * @apiParam {string} data (optional) meta data string of geometry object
     * @apiParam {int} country (optional) The owning country id. NULL or -1 if no country is set.
     * @apiSuccess {int} id of the newly created geometry
     * @return int|PromiseInterface
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Post(
        int $layer,
        string $geometry,
        string $FID = "",
        int $persistent = null,
        string $data = "",
        int $country = null,
        int $plan = -1
    )/*: int|PromiseInterface // <-- php 8 */ {
        if ($country == -1) {
            $country = null;
        }

        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->insert('geometry')
                ->values([
                    'geometry_layer_id' => $qb->createPositionalParameter($layer),
                    'geometry_geometry' => $qb->createPositionalParameter($geometry),
                    'geometry_FID' => $qb->createPositionalParameter($FID),
                    'geometry_persistent' => $qb->createPositionalParameter($persistent),
                    'geometry_data' => $qb->createPositionalParameter($data),
                    'geometry_country_id' => $qb->createPositionalParameter($country)
                ])
        )
        ->done(
            function (Result $result) use ($deferred, $plan, $persistent) {
                if (null === $newId = $result->getLastInsertedId()) {
                    $deferred->reject();
                    return null;
                }

                $toPromiseFunctions = [];
                if ($plan != -1) {
                    $toPromiseFunctions[] = tpf(function () use ($plan) {
                        $qb = $this->getAsyncDatabase()->createQueryBuilder();
                        return $this->getAsyncDatabase()->query(
                            $qb
                                ->update('plan')
                                ->set('plan_lastupdate', $qb->createPositionalParameter(microtime(true)))
                                ->where($qb->expr()->eq('plan_id', $qb->createPositionalParameter($plan)))
                        );
                    });
                }
                if (null == $persistent) {
                    $toPromiseFunctions[] = tpf(function () use ($newId) {
                        $qb = $this->getAsyncDatabase()->createQueryBuilder();
                        return $this->getAsyncDatabase()->query(
                            $qb
                                ->update('geometry')
                                ->set('geometry_persistent', $qb->createPositionalParameter($newId))
                                ->where($qb->expr()->eq('geometry_id', $qb->createPositionalParameter($newId)))
                        );
                    });
                }

                if (empty($toPromiseFunctions)) {
                    $deferred->resolve($newId);
                    return null;
                }

                parallel($toPromiseFunctions)
                    ->done(
                        function () use ($deferred, $newId) {
                            $deferred->resolve($newId);
                        },
                        function ($reason) use ($deferred) {
                            $deferred->reject($reason);
                        }
                    );
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );

        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/PostSubtractive Post Subtractive
     * @apiDescription Create a new subtractive polygon on an existing polygon
     * @apiParam {int} layer id of layer to post in
     * @apiParam {string} geometry string of geometry to post
     * @apiParam {int} subtractive id of the polygon the newly created polygon is subtractive to
     * @apiParam {string} FID (optional) FID of geometry
     * @apiParam {string} persistent (optional) persistent ID of geometry
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function PostSubtractive(
        int $layer,
        string $geometry,
        int $subtractive,
        int $persistent = null,
        string $FID = ""
    ): ?PromiseInterface {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->insert('geometry')
                ->values([
                    'geometry_layer_id' => $qb->createPositionalParameter($layer),
                    'geometry_geometry' => $qb->createPositionalParameter($geometry),
                    'geometry_FID' => $qb->createPositionalParameter($FID),
                    'geometry_persistent' => $qb->createPositionalParameter($persistent),
                    'geometry_subtractive' => $qb->createPositionalParameter($subtractive)
                ])
        )
        ->then(function (Result $result) use ($persistent) {
            if (null === $id = $result->getLastInsertedId()) {
                throw new Exception('Could not retrieve last inserted id');
            }
            //set the persistent id if it's new geometry
            if (null === $persistent) {
                return $this->getAsyncDatabase()->update(
                    'geometry',
                    [
                        'geometry_id' => $id
                    ],
                    [
                        'geometry_persistent' => $id
                    ]
                );
            }
            return null; // nothing to do.
        })
        ->done(
            function (/* ?Result $result */) use ($deferred) {
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
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/update Update
     * @apiParam {int} id geometry id to update
     * @apiParam {string} geometry string of geometry json to post
     * @apiParam {int} country country id to set as geometry's owner
     * @apiSuccess {int} id same geometry id
     * @return int|PromiseInterface
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Update(int $id, int $country, string $geometry)/*: int|PromiseInterface // <-- php 8 */
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('geometry')
                ->set('geometry_geometry', $qb->createPositionalParameter($geometry))
                ->set('geometry_country_id', $qb->createPositionalParameter($country))
                ->where($qb->expr()->eq('geometry_id', $qb->createPositionalParameter($id)))
        )
        ->done(
            function (Result $result) use ($deferred, $id) {
                $deferred->resolve($id);
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );
        $promise = $deferred->promise();
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/Data Data
     * @apiDescription Adjust geometry metadata and type
     * @apiParam {int} id geometry id to update
     * @apiParam {string} data metadata of the geometry to set
     * @apiParam {string} type type value, either single integer or comma-separated multiple integers
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Data(string $data, string $type, int $id): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('geometry')
                ->set('geometry_data', $qb->createPositionalParameter($data))
                ->set('geometry_type', $qb->createPositionalParameter($type))
                ->where($qb->expr()->eq('geometry_id', $qb->createPositionalParameter($id)))
        )
        ->done(
            function (Result $result) use ($deferred) {
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
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/Delete Delete
     * @apiDescription Delete geometry without using a plan
     * @apiParam {int} id geometry id to delete, marks a row as inactive
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Delete(int $id): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->update('geometry')
                ->set('geometry_active', '0')
                ->set('geometry_deleted', '1')
                ->where(
                    $qb->expr()->or(
                        $qb->expr()->eq('geometry_id', $qb->createPositionalParameter($id))
                    )
                    ->with($qb->expr()->eq('geometry_subtractive', $qb->createPositionalParameter($id)))
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
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/MarkForDelete MarkForDelete
     * @apiDescription Delete geometry using a plan, this will be triggered at the execution time of a plan
     * @apiParam {int} id geometry persistent id to delete
     * @apiParam {int} plan plan id where the geometry will be deleted
     * @apiParam {int} layer the layer id where the geometry will be deleted
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function MarkForDelete(int $plan, int $id, int $layer): ?PromiseInterface
    {
        $deferred = new Deferred();
        $this->getAsyncDatabase()->insert('plan_delete', [
            'plan_delete_plan_id' => $plan,
            'plan_delete_geometry_persistent' => $id,
            'plan_delete_layer_id' => $layer
        ])
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
     * @apiGroup Geometry
     * @throws Exception
     * @api {POST} /geometry/UnmarkForDelete UnmarkForDelete
     * @apiDescription Remove the deletion of a geometry put in the plan
     * @apiParam {int} id geometry persistent id to undelete
     * @apiParam {int} plan plan id where the geometry is located in
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function UnmarkForDelete(int $plan, int $id): ?PromiseInterface
    {
        $deferred = new Deferred();
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->delete('plan_delete')
                ->where(
                    $qb->expr()->and(
                        $qb->expr()->eq('plan_delete_plan_id', $qb->createPositionalParameter($plan))
                    )
                    ->with($qb->expr()->eq('plan_delete_geometry_persistent', $qb->createPositionalParameter($id)))
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
}
