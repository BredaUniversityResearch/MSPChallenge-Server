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
        "Update",
        "SetShippingIssues"
    );
    
    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    private function postHandleRemovals(array $removed): PromiseInterface
    {
        if (empty($removed)) {
            return resolveOnFutureTick(new Deferred())->promise();
        }

        // $removed can be simplified to only hold the issue_database_id,
        //   also key on issue_database_id, removing duplicates
        $removed = collect($removed)
            ->keyBy('issue_database_id')
            ->keys()
            ->all();

        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
            $qb
                ->update('warning')
                ->set('warning_active', 0)
                ->set('warning_last_update', $qb->createPositionalParameter(microtime(true)))
                ->where($qb->expr()->in('warning_id', $removed))
        );
    }

    private function postHandleAddition(array $addedIssue): PromiseInterface
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
                    $addedIssue['plan_layer_id'], $addedIssue['type'], $addedIssue['x'], $addedIssue['y'],
                    $addedIssue['source_plan_id'], $addedIssue['restriction_id']
                ])
        )
        ->then(function (Result $result) use ($addedIssue) {
            $existingIssues = $result->fetchAllRows();
            if (empty($existingIssues)) {
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $this->getAsyncDatabase()->query(
                    $qb
                        ->insert('warning')
                        ->values([
                            'warning_last_update' => $qb->createPositionalParameter(microtime(true)),
                            'warning_active' => 1,
                            'warning_layer_id' => $qb->createPositionalParameter($addedIssue['plan_layer_id']),
                            'warning_issue_type' => $qb->createPositionalParameter($addedIssue['type']),
                            'warning_x' => $qb->createPositionalParameter($addedIssue['x']),
                            'warning_y' => $qb->createPositionalParameter($addedIssue['y']),
                            'warning_source_plan_id' => $qb->createPositionalParameter(
                                $addedIssue['source_plan_id']
                            ),
                            'warning_restriction_id' => $addedIssue['restriction_id']
                        ])
                );
            }

            $existingIssue = current($existingIssues);
            $toPromiseFunctions[$existingIssue['warning_id']] = tpf(function () use ($existingIssue) {
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                return $this->getAsyncDatabase()->query(
                    $qb
                        ->update('warning')
                        ->set('warning_last_update', $qb->createPositionalParameter(microtime(true)))
                        ->set('warning_active', 1)
                        ->where($qb->expr()->eq(
                            'warning_id',
                            $qb->createPositionalParameter($existingIssue['warning_id'])
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
                            ->set('warning_last_update', $qb->createPositionalParameter(microtime(true)))
                            ->set('warning_active', 0)
                            ->where($qb->expr()->eq(
                                'warning_id',
                                $qb->createPositionalParameter($existingIssue['warning_id'])
                            ))
                    );
                });
            }
            return parallel($toPromiseFunctions);
        });
    }

    private function postHandleAdditions(array $added): PromiseInterface
    {
        $toPromiseFunctions = [];
        foreach ($added as $addedIssue) {
            $toPromiseFunctions[] = tpf(function () use ($addedIssue) {
                return $this->postHandleAddition($addedIssue);
            });
        }
        return parallel($toPromiseFunctions);
    }

    /**
     * @apiGroup Warning
     * @throws Exception
     * @api {POST} /warning/post Post
     * @apiParam {added} Json array of IssueObjects that are added.
     * @apiParam {removed} Json array of IssueObjects that are removed.
     * @apiDescription Add or update a warning message on the server
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Post(array $added, array $removed): ?PromiseInterface
    {
        $deferred = new Deferred();
        $toPromiseFunctions[] = tpf(function () use ($added) {
            return $this->postHandleAdditions($added);
        });
        $toPromiseFunctions[] = tpf(function () use ($removed) {
            return $this->postHandleRemovals($removed);
        });
        parallel($toPromiseFunctions)
            ->done(
                function (/* array $results */) use ($deferred) {
                    $deferred->resolve(); // return void, we do not care about the result
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
    public function RemoveAllWarningsForLayer(int $layerId): void
    {
        Database::GetInstance()->query(
            "UPDATE warning SET warning_active = 0, warning_last_update = ? WHERE warning_source_plan_id = ?",
            array(microtime(true), $layerId)
        );
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
        Database::GetInstance()->query(
            "
            UPDATE shipping_warning SET shipping_warning_active = 0, shipping_warning_lastupdate = ?
            WHERE shipping_warning_active = 1
            ",
            array(microtime(true))
        );

        $newIssues = json_decode($issues, true);
        foreach ($newIssues as $issue) {
            Database::GetInstance()->query(
                "
                INSERT INTO shipping_warning (
                    shipping_warning_lastupdate, shipping_warning_source_geometry_persistent_id,
                    shipping_warning_destination_geometry_persistent_id, shipping_warning_message
                ) VALUES(?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE shipping_warning_active = 1, shipping_warning_lastupdate = ?
				",
                array(
                    microtime(true), $issue['source_geometry_persistent_id'],
                    $issue['destination_geometry_persistent_id'], $issue['message'], microtime(true)
                )
            );
        }
    }
}
