<?php

namespace App\Domain\API\v1;

use Drift\DBAL\Result;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\resolveOnFutureTick;
use function App\tpf;
use function App\await;

class Warning extends Base
{
    private const ALLOWED = array(
        "Post",
        "SetShippingIssues"
    );
    
    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    private function postHandleRemovals(array $removed): PromiseInterface
    {
        $removed = array_map(fn($x) => (string)$x, filter_var_array($removed, FILTER_VALIDATE_INT));
        if (empty($removed)) {
            return resolveOnFutureTick(new Deferred())->promise();
        }

        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
            $qb
                ->update('warning')
                ->set('warning_active', '0')
                ->set('warning_last_update', 'UNIX_TIMESTAMP(NOW(6))')
                ->where($qb->expr()->in('warning_id', $removed))
        );
    }

    /**
     * @throws Exception
     */
    private function postHandleAddition(int $planId, int $planLayerId, array $addedIssue): PromiseInterface
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
            $qb
                ->select('warning_id')
                ->from('warning')
                ->where('warning_active = 1')
                ->andWhere('warning_layer_id = ?')
                ->andWhere('warning_issue_type = ?')
                ->andWhere('abs(warning_x - ?) <= 1e-6')
                ->andWhere('abs(warning_y - ?) <= 1e-6')
                ->andWhere('warning_source_plan_id = ?')
                ->andWhere('warning_restriction_id = ?')
                ->setParameters([
                    $planLayerId, $addedIssue['type'], $addedIssue['x'], $addedIssue['y'],
                    $planId, $addedIssue['restriction_id']
                ])
        )
        ->then(function (Result $result) use ($planId, $planLayerId, $addedIssue) {
            $existingIssues = $result->fetchAllRows();
            if (empty($existingIssues)) {
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $this->getAsyncDatabase()->query(
                    $qb
                        ->insert('warning')
                        ->values([
                            'warning_last_update' => 'UNIX_TIMESTAMP(NOW(6))',
                            'warning_active' => 1,
                            'warning_layer_id' => $qb->createPositionalParameter($planLayerId),
                            'warning_issue_type' => $qb->createPositionalParameter($addedIssue['type']),
                            'warning_x' => $qb->createPositionalParameter($addedIssue['x']),
                            'warning_y' => $qb->createPositionalParameter($addedIssue['y']),
                            'warning_source_plan_id' => $qb->createPositionalParameter($planId),
                            'warning_restriction_id' => $addedIssue['restriction_id']
                        ])
                )
                ->then(function (Result $result) use ($addedIssue) {
                    return $addedIssue;
                });
            }

            $existingIssue = current($existingIssues);
            $warningIdUpdated = $existingIssue['warning_id'];
            $toPromiseFunctions[$warningIdUpdated] = tpf(function () use ($warningIdUpdated) {
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $this->getAsyncDatabase()->query(
                    $qb
                        ->update('warning')
                        ->set('warning_last_update', 'UNIX_TIMESTAMP(NOW(6))')
                        ->set('warning_active', '1')
                        ->where($qb->expr()->eq(
                            'warning_id',
                            $qb->createPositionalParameter($warningIdUpdated)
                        ))
                );
            });

            // if (count($existingIssue) > 1)
            // Something has already gone horribly wrong, try to save it by disabling all the other issues that
            //   we found that match our data.
            while ($existingIssue = next($existingIssues)) {
                $toPromiseFunctions[$existingIssue['warning_id']] = tpf(function () use ($existingIssue) {
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    return $this->getAsyncDatabase()->query(
                        $qb
                            ->update('warning')
                            ->set('warning_last_update', 'UNIX_TIMESTAMP(NOW(6))')
                            ->set('warning_active', '0')
                            ->where($qb->expr()->eq(
                                'warning_id',
                                $qb->createPositionalParameter($existingIssue['warning_id'])
                            ))
                    );
                });
            }
            // todo: we might not need to await the result of the updates...and already return the $warningIdUpdated ?
            //   so like this:
            //     parallel($toPromiseFunctions); // to execute them
            //     return $warningIdUpdated; // but not awaiting the results, notice the lack of "->then(...)"
            //   but might have impact on the "latest" response data, so for safety, we just wait for them to finish
            return parallel($toPromiseFunctions)
                ->then(function (/*array $results*/) use ($addedIssue) {
                    return $addedIssue;
                });
        });
    }

    private function postHandleAdditions(int $planId, int $planLayerId, array $added): PromiseInterface
    {
        $toPromiseFunctions = [];
        foreach ($added as $addedIssue) {
            $toPromiseFunctions[] = tpf(function () use ($planId, $planLayerId, $addedIssue) {
                return $this->postHandleAddition($planId, $planLayerId, $addedIssue);
            });
        }
        return parallel($toPromiseFunctions);
    }

    /**
     * @apiGroup Warning
     * @throws Exception
     * @api {POST} /warning/post Post
     * @apiParam {int} plan plan id
     * @apiParam {int} planlayer_id id of the plan layer
     * @apiParam {array} added Json array of IssueObjects that are added.
     * @apiParam {arrat} removed Json array of IssueObjects that are removed.
     * @apiDescription Add or update a warning message on the server
     * @noinspection SpellCheckingInspection
     * @return array{
     *   array{
     *     issue_database_id: int, type: string, active: boolean, x: float, y: float, restriction_id: int
     *   }
     * }|PromiseInterface
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Post(int $plan, int $planlayer_id, array $added, array $removed = []): array|PromiseInterface
    {
        $deferred = new Deferred();
        $toPromiseFunctions[] = tpf(function () use ($plan, $planlayer_id, $added) {
            return $this->postHandleAdditions($plan, $planlayer_id, $added);
        });
        if (!empty($removed)) {
            $toPromiseFunctions[] = tpf(function () use ($removed) {
                return $this->postHandleRemovals($removed);
            });
        }
        parallel($toPromiseFunctions)
            ->done(
                function (/* array $results */) use ($deferred, $planlayer_id) {
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    $this->getAsyncDatabase()->query(
                        $qb
                            ->select(
                                'warning_id as issue_database_id',
                                'warning_issue_type as type',
                                'warning_active as active',
                                'warning_restriction_id as restriction_id',
                                'warning_x as x',
                                'warning_y as y'
                            )
                            ->from('warning')
                            ->where('warning_active = 1')
                            ->andWhere(
                                $qb->expr()->eq('warning_layer_id', $qb->createPositionalParameter($planlayer_id))
                            )
                    )
                    ->done(function (Result $result) use ($deferred) {
                        $deferred->resolve(
                            collect(($result->fetchAllRows() ?? []) ?: [])
                                ->map(function ($issue) {
                                    $issue['active'] = $issue['active'] === '1';
                                    return $issue;
                                })->all()
                        );
                    });
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
    public function RemoveAllWarningsForLayer(int $layerId, bool $hardDelete = false): ?PromiseInterface
    {
        $promise = $this->getAsyncDatabase()->delete('warning', ['warning_source_plan_id' => $layerId])
            ->then(function (/* Result $result */) use ($layerId) {
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $this->getAsyncDatabase()->query(
                    $qb
                        ->update('warning')
                        ->set('warning_active', $qb->createPositionalParameter(0))
                        ->set('warning_last_update', 'UNIX_TIMESTAMP(NOW(6))')
                        ->where($qb->expr()->eq('warning_source_plan_id', $qb->createPositionalParameter($layerId)))
                );
            });
        return $this->isAsync() ? $promise : await($promise);
    }

    /**
     * @apiGroup Warning
     * @throws Exception
     * @api {POST} /warning/SetShippingIssues Set shipping issues
     * @apiParam {string} issues The JSON encoded issues of SEL.APIShippingIssue type.
     * @apiDescription Clears out the old shipping issues and creates new shipping issues defined by issues
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SetShippingIssues(string $issues): void
    {
        $this->getDatabase()->query(
            "
            UPDATE shipping_warning SET shipping_warning_active=0, shipping_warning_lastupdate=UNIX_TIMESTAMP(NOW(6))
            WHERE shipping_warning_active = 1
            "
        );

        $newIssues = json_decode($issues, true);
        foreach ($newIssues as $issue) {
            $this->getDatabase()->query(
                "
                INSERT INTO shipping_warning (
                    shipping_warning_lastupdate, shipping_warning_source_geometry_persistent_id,
                    shipping_warning_destination_geometry_persistent_id, shipping_warning_message
                ) VALUES(UNIX_TIMESTAMP(NOW(6)), ?, ?, ?)
				ON DUPLICATE KEY UPDATE shipping_warning_active=1, shipping_warning_lastupdate=UNIX_TIMESTAMP(NOW(6))
				",
                array(
                    $issue['source_geometry_persistent_id'],
                    $issue['destination_geometry_persistent_id'], $issue['message']
                )
            );
        }
    }
}
