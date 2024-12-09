<?php

namespace App\Domain\API\v1;

use App\Domain\WsServer\ExecuteBatchRejection;
use Doctrine\DBAL\Types\Types;
use Drift\DBAL\ConnectionPool;
use Drift\DBAL\Result;
use Drift\DBAL\SingleConnection;
use Exception;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function App\chain;
use function App\parallel;
use function App\query;
use function App\tpf;
use function React\Promise\reject;

class Batch extends Base
{
    private const REFERENCE_SPECIFIER = "!Ref:";

    private array $cachedBatchResults = [];

    private const ALLOWED = array(
        "ExecuteBatch"
    );
    
    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @throws Exception
     */
    private function startBatch(int $countryId, int $userId, string $batchGuid): int
    {
        $this->getDatabase()->query(
            <<< 'SQL'
            DELETE FROM api_batch_task WHERE api_batch_task_batch_id IN (
                SELECT api_batch_id FROM api_batch WHERE api_batch_guid = ?
            )
            SQL,
            array($batchGuid),
        );
        $id = $this->getDatabase()->query(
            <<< 'SQL'
            INSERT INTO api_batch(
                api_batch_country_id,
                api_batch_user_id,
                api_batch_guid
            ) VALUES (:countryId, :userId, :batchGuid) 
                ON DUPLICATE KEY UPDATE
                    api_batch_state='Setup',
                    api_batch_country_id=:countryId,
                    api_batch_user_id=:userId,
                    api_batch_server_id=NULL,
                    api_batch_communicated=0,
                    api_batch_lastupdate=0
            SQL,
            array('countryId' => $countryId, 'userId' => $userId, 'batchGuid' => $batchGuid),
            true
        );
        if (empty($id)) { // empty array (no connection), or false (insert failed)
            return 0;
        }
        return (int)$id;
    }

    /**
     * @param int $batchId
     * @param array{int: array{call_id: int, group: string, end_point: string, endpoint_data: string}} $requests
     * @throws Exception
     */
    private function addToBatch(
        int $batchId,
        array $requests
    ): void {
        $requests = collect($requests)->map(function ($r) {
            $r['endpoint'] = preg_replace("/\A[\/]?api\//", "", $r['endpoint']);
            return $r;
        })->all();
        foreach ($requests as $r) {
            $this->getDatabase()->query(
                "INSERT INTO api_batch_task (
                api_batch_task_batch_id, 
                api_batch_task_group, 
                api_batch_task_reference_identifier, 
                api_batch_task_api_endpoint, 
                api_batch_task_api_endpoint_data)
                VALUES (?, ?, ?, ?, ?)",
                array(
                    $batchId,
                    $r['group'],
                    $r['call_id'],
                    $r['endpoint'],
                    $r['endpoint_data']
                ),
                true
            );
        }
    }

    /**
     * @apiGroup Batch
     * @throws Exception
     * @api {POST} /batch/executebatch ExecuteBatch
     * @apiDescription execute batch
     *
     * @apiParam {int} $country_id id of country/team for which the batch was started
     * @apiParam {int} $user_id id of user for which the batch was started
     * @apiParam {string} batch_guid Batch Guid to execute.
     * @apiParam {string} requests Json data containg the batch requests
     * @apiParam {bool} async Execute this batch asynchronous. Results of the executed batch will be communicated
     *   through the websocket connection.
     *
     * @apiSuccess {object} results Results of the executed batch. JSON object containing client-specified call_id
     *   (if non-empty) and payload which is the result of the call.
     * When failed this object contains failed_task_id which references the execution_task_id returned in the
     *   AddToBatch.
     * @noinspection PhpUnused
     * @return string
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ExecuteBatch(
        int $country_id,
        int $user_id,
        string $batch_guid,
        string $requests,
    ): string {
        if (0 === $batchId = $this->startBatch($country_id, $user_id, $batch_guid)) {
            throw new Exception("Unable to start batch: ".$batch_guid);
        }
        // @var null|array{int: array{call_id: int, group: string, end_point: string, endpoint_data: string}}
        if (null === $requests = json_decode($requests, true)) {
            throw new Exception("Unable to decode requests for batch: ".$batch_guid);
        }

        // add to batch inserts in a db transaction
        $this->getDatabase()->DBStartTransaction();
        try {
            $this->addToBatch($batchId, $requests);
        } catch (Exception $e) {
            $this->getDatabase()->DBRollbackTransaction();
            throw $e;
        }
        $this->getDatabase()->DBCommitTransaction();

        $data = $this->getDatabase()->query("SELECT api_batch_task_id, 
                api_batch_task_reference_identifier, 
                api_batch_task_api_endpoint, 
                api_batch_task_api_endpoint_data 
            FROM api_batch_task 
            WHERE api_batch_task_batch_id = ? 
            ORDER BY api_batch_task_group", array($batchId));
        if (empty($data)) {
            throw new Exception("Tried to execute an empty batch");
        }

        // queue it
        $this->getDatabase()->query(
            'UPDATE api_batch SET api_batch_state=\'Queued\' WHERE api_batch_id = ?',
            array($batchId)
        );

        // no results yet, will be sent later through websocket connection
        return '';
    }

    public function executeNextQueuedBatchFor(
        int $teamId,
        int $userId,
        string $serverId,
        callable $onExecuteQueuedBatchesFunction
    ): PromiseInterface {
        $deferred = new Deferred();

        // get batches to execute
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $this->getAsyncDatabase()->query(
            $qb
                ->select('b.api_batch_guid')
                ->from('api_batch', 'b')
                ->where(
                    $qb->expr()->and(
                        'b.api_batch_state = "Queued"',
                        'b.api_batch_country_id = ' . $qb->createPositionalParameter($teamId),
                        'b.api_batch_user_id = ' . $qb->createPositionalParameter($userId)
                    )
                )
                ->orderBy('api_batch_id') // first in, first out
                ->setMaxResults(1)
        )
        ->then(function (Result $result) use ($serverId, $onExecuteQueuedBatchesFunction) {
            if (null === $row = $result->fetchFirstRow()) {
                return [];
            }
            $onExecuteQueuedBatchesFunction();
            $batchGuid = $row['api_batch_guid'];
            return $this->executeQueuedBatch($batchGuid, $serverId)
                ->otherwise(function ($reason) use ($batchGuid) {
                    return reject(new ExecuteBatchRejection($batchGuid, $reason));
                });
        })
        ->done(
            function (array $batchResultContainer) use ($deferred) {
                $deferred->resolve($batchResultContainer);
            },
            function ($reason) use ($deferred) {
                $deferred->reject($reason);
            }
        );

        return $deferred->promise();
    }

    /**
     * @throws Exception
     */
    public function setCommunicated(string $batchGuid): Promise
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getAsyncDatabase()->query(
            $qb
                ->update('api_batch')
                ->set('api_batch_communicated', $qb->createPositionalParameter(true, Types::BOOLEAN))
                ->where($qb->expr()->eq('api_batch_guid', $qb->createPositionalParameter($batchGuid)))
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function executeQueuedBatch(string $batchGuid, string $serverId): Promise
    {
        // get batch tasks to execute
        $this->cachedBatchResults[$batchGuid] = [];
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return query(
            $this->getAsyncDatabase(),
            $qb
                ->select('t.*')
                ->from('api_batch_task', 't')
                ->innerJoin(
                    't',
                    'api_batch',
                    'b',
                    $qb->expr()->and(
                        'b.api_batch_id = t.api_batch_task_batch_id',
                        'b.api_batch_state = "Queued"',
                        $qb->expr()->eq('b.api_batch_guid', $qb->createPositionalParameter($batchGuid))
                    )
                )
        )
        ->then(function (Result $result) use ($batchGuid, $serverId) {
            $deferred = new Deferred();
            // first set the state to "Executing" before continuing
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            $this->getAsyncDatabase()->query(
                $qb
                    ->update('api_batch')
                    ->set('api_batch_server_id', $qb->createPositionalParameter($serverId))
                    ->set('api_batch_state', $qb->createPositionalParameter('Executing'))
                    ->where($qb->expr()->eq('api_batch_guid', $qb->createPositionalParameter($batchGuid)))
            )
            ->done(
                function (Result $dummy) use ($deferred, $result) {
                    // just pass the original result.
                    $deferred->resolve($result);
                },
                function () use ($deferred, $batchGuid) {
                    $deferred->reject('Could not set to status "Executing" for batch guid: ' . $batchGuid);
                }
            );
            return $deferred->promise();
        })
        ->then(function (Result $result) use ($batchGuid) {
            $apiBatchTasks = ($result->fetchAllRows() ?? []) ?: [];
            // let's make the batch transactional
            /** @var ConnectionPool $pool */
            $pool = $this->getAsyncDatabase();
            // so we are forcing a transactional connection here, passed through any subsequent async calls later on,
            //  using asyncDataTransferTo
            return $pool->startTransaction()->then(fn ($conn) => $this->setAsyncDatabase($conn))->then(function () use (
                $apiBatchTasks,
                $batchGuid,
                $pool
            ) {
                $groupToBatchTasks = collect($apiBatchTasks)
                    ->groupBy('api_batch_task_group')
                    ->sortKeys()
                    ->all();

                $chain = [];
                foreach ($groupToBatchTasks as $groupId => $batchTasks) {
                    $parallel = [];
                    /** @var array $task */
                    foreach ($batchTasks as $task) {
                        $callData = json_decode($task['api_batch_task_api_endpoint_data'], true);
                        $endpoint = $task['api_batch_task_api_endpoint'];

                        // create ObjectMethod and inject game session id, and async database into the instance.
                        $objectMethod = Router::createObjectMethodFromEndpoint($endpoint);
                        /** @var Base $instance */
                        $instance = $objectMethod->getInstance();
                        $this->asyncDataTransferTo($instance);

                        $parallel[$task['api_batch_task_reference_identifier']] = tpf(
                            function () use (
                                $objectMethod,
                                $callData,
                                $batchGuid,
                                $task
                            ) {
                                return Router::executeCallAsync(
                                    $objectMethod,
                                    $callData,
                                    function (array &$callData) use ($batchGuid) {
                                        // fix references in call data using batch cache results
                                        array_walk_recursive(
                                            $callData,
                                            function (&$value, $key, array $presentResults) {
                                                self::fixupReferences($value, $key, $presentResults);
                                            },
                                            $this->cachedBatchResults[$batchGuid]
                                        );
                                    },
                                    function (&$payload) use ($batchGuid, $task) {
                                        // fill batch cache results
                                        $this->cachedBatchResults[$batchGuid]
                                            [$task['api_batch_task_reference_identifier']] = $payload;
                                    }
                                );
                            }
                        );
                    }
                    $chain[$groupId] = tpf(function () use ($parallel) {
                        return parallel($parallel);
                    });
                }

                return chain($chain)
                    ->then(function (array $taskResultsContainer) use ($batchGuid, $pool) {
                        /** @var SingleConnection $conn */
                        $conn = $this->getAsyncDatabase();
                        $pool->commitTransaction($conn);
                        $qb = $this->getAsyncDatabase()->createQueryBuilder();
                        return $this->getAsyncDatabase()->query(
                            $qb
                                ->update('api_batch')
                                ->set('api_batch_state', $qb->createPositionalParameter('Success'))
                                ->set('api_batch_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
                                ->where($qb->expr()->eq('api_batch_guid', $qb->createPositionalParameter($batchGuid)))
                        )
                            ->then(
                                function (/* Result $result */) use ($taskResultsContainer, $batchGuid) {
                                    $batchResult = [];
                                    foreach ($taskResultsContainer as $taskResults) {
                                        foreach ($taskResults as $taskId => $taskResult) {
                                            $batchResult[$batchGuid]['results'][] = [
                                                'call_id' => $taskId,
                                                'payload' => json_encode($taskResult) ?: null
                                            ];
                                        }
                                    }
                                    return $batchResult;
                                }
                            );
                    })
                    ->otherwise(function ($reason) use ($batchGuid, $pool) {
                        /** @var SingleConnection $conn */
                        $conn = $this->getAsyncDatabase();
                        $pool->rollbackTransaction($conn);
                        // run async query to set batches to failed, no need to wait for the result.
                        $qb = $this->getAsyncDatabase()->createQueryBuilder();
                        $this->getAsyncDatabase()->query(
                            $qb
                                ->update('api_batch')
                                ->set('api_batch_state', $qb->createPositionalParameter('Failed'))
                                ->set('api_batch_lastupdate', 'UNIX_TIMESTAMP(NOW(6))')
                                ->where($qb->expr()->eq('api_batch_guid', $qb->createPositionalParameter($batchGuid)))
                        );
                        // Propagate by returning rejection
                        return reject($reason);
                    })
                    ->always(function () use ($batchGuid) {
                        // clean up batch cache results
                        unset($this->cachedBatchResults[$batchGuid]);
                    });
            });
        });
    }

    /**
     * @param mixed $value
     * @param mixed $key
     * @param array $presentResults
     * @throws Exception
     */
    private static function fixupReferences(&$value, $key, array $presentResults): void
    {
        $refSpecifierLength = strlen(self::REFERENCE_SPECIFIER);

        if (!is_string($value) ||
            substr($value, 0, $refSpecifierLength) != self::REFERENCE_SPECIFIER) {
            return;
        }

        $firstArrayAccessor = strpos($value, '[');
        $childSpecifiers = null;
        if ($firstArrayAccessor !== false) {
            $firstArrayAccessor -= $refSpecifierLength;
            if (1 === preg_match_all("/\[(?<Accessor>[a-zA-Z0-9]+)]*/", $value, $matches)) {
                $childSpecifiers = $matches["Accessor"];
            }
        } else {
            $firstArrayAccessor = PHP_INT_MAX;
        }
        $callId = substr($value, $refSpecifierLength, $firstArrayAccessor);

        if (!array_key_exists($callId, $presentResults)) {
            throw new Exception("Could not find result referenced by \"$value\". Reference Key \"$callId\" not found ".
                "in present results \"".var_export($presentResults, true)."\"");
        }
        $targetResult = $presentResults[$callId];
        if ($childSpecifiers != null) {
            foreach ($childSpecifiers as $childSpecifier) {
                if (array_key_exists($childSpecifier, $targetResult)) {
                    $targetResult = $targetResult[$childSpecifier];
                }
            }
        }
        $value = $targetResult;
    }
}
