<?php

namespace App\Domain\API\v1;

use Drift\DBAL\Result;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\tpf;
use function Clue\React\Block\await;
use function React\Promise\all;

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

    private function postAsyncHandleRemovals(array $removed): ?PromiseInterface
    {
        if (empty($removed)) {
            return null;
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

    private function postAsyncHandleAdditions(array $added): PromiseInterface
    {
        $promises = [];
        foreach ($added as $addedIssue) {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            $promises[] = $this->getAsyncDatabase()->query(
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
                $qb = $this->getAsyncDatabase()->createQueryBuilder();
                $promises[$existingIssue['warning_id']] = $this->getAsyncDatabase()->query(
                    $qb
                        ->update('warning')
                        ->set('warning_last_update', $qb->createPositionalParameter(microtime(true)))
                        ->set('warning_active', 1)
                        ->where($qb->expr()->eq(
                            'warning_id',
                            $qb->createPositionalParameter($existingIssue['warning_id'])
                        ))
                );

                // if (count($existingIssue) > 1)
                // Something has already gone horribly wrong, try to save it by disabling all the other issues that
                //   we found that match our data.
                while ($existingIssue = next($existingIssues)) {
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    $promises[$existingIssue['warning_id']] = $this->getAsyncDatabase()->query(
                        $qb
                            ->update('warning')
                            ->set('warning_last_update', $qb->createPositionalParameter(microtime(true)))
                            ->set('warning_active', 0)
                            ->where($qb->expr()->eq(
                                'warning_id',
                                $qb->createPositionalParameter($existingIssue['warning_id'])
                            ))
                    );
                }
                return all($promises);
            });
        }
        return all($promises);
    }

    public function postAsync(array $added, array $removed): PromiseInterface
    {
        $deferred = new Deferred();
        $promises[] = $this->postAsyncHandleAdditions($added);
        if (null !== $promise = $this->postAsyncHandleRemovals($removed)) {
            $promises[] = $promise;
        }
        all($promises)
            ->done(
                function () use ($deferred) {
                    $deferred->resolve(); // return void, we do not care about the result
                },
                function ($reason) use ($deferred) {
                    $deferred->reject($reason);
                }
            );
        return $deferred->promise();
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
    public function Post(array $added, array $removed): void
    {
        await($this->postAsync($added, $removed));
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

    /**
     * @throws Exception
     */
    public function latest(float $time): PromiseInterface
    {
        $toPromiseFunctions[] = tpf(function () use ($time) {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select(
                        'warning_id as issue_database_id',
                        'warning_active as active',
                        'warning_layer_id as plan_layer_id',
                        'warning_issue_type as type',
                        'warning_x as x',
                        'warning_y as y',
                        'warning_source_plan_id as source_plan_id',
                        'warning_restriction_id as restriction_id'
                    )
                    ->from('warning')
                    ->where('warning_last_update > ' . $qb->createPositionalParameter($time))
            );
        });
        $toPromiseFunctions[] = tpf(function () use ($time) {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select(
                        'shipping_warning_id as warning_id',
                        'shipping_warning_source_geometry_persistent_id as source_geometry_persistent_id',
                        'shipping_warning_destination_geometry_persistent_id as destination_geometry_persistent_id',
                        'shipping_warning_message as message',
                        'shipping_warning_active as active'
                    )
                    ->from('shipping_warning')
                    ->where('shipping_warning_lastupdate > ' . $qb->createPositionalParameter($time))
            );
        });
        return parallel($toPromiseFunctions, 1); // todo: if performance allows, increase threads
    }
}
