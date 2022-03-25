<?php

namespace App\Domain\API\v1;

use Drift\DBAL\Result;
use Exception;
use Illuminate\Support\Collection;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\chain;
use function App\resolveOnFutureTick;
use function React\Promise\reject;

class Batch extends Base
{
    private const REFERENCE_SPECIFIER = "!Ref:";

    private array $cachedBatchResults = [];

    private const ALLOWED = array(
        "StartBatch",
        "AddToBatch",
        "ExecuteBatch"
    );
    
    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup Batch
     * @throws Exception
     * @api {POST} /batch/startbatch StartBatch
     * @apiDescription Starts a new batch
     *
     * @apiParam {int} $country_id id of country/team for which the batch was started
     * @apiParam {int} $user_id id of user for which the batch was started
     *
     * @apiSuccess {int} batch_id Batch ID used to identify this batch.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function StartBatch(int $country_id, int $user_id)
    {
        return Database::GetInstance()->query(
            "INSERT INTO api_batch(api_batch_country_id, api_batch_user_id) VALUES (?, ?)",
            array($country_id, $user_id),
            true
        );
    }

    /**
     * @apiGroup Batch
     * @throws Exception
     * @api {POST} /batch/addtobatch AddToBatch
     * @apiDescription Starts a new batch
     *
     * @apiParam {int} batch_id Batch ID to add to
     * @apiParam {int} batch_group Batch execution group
     * @apiParam {string} call_id Client defined identfier that is unique within the current batch_id.
     * @apiParam {string} endpoint Endpoint for this batch call to make. E.g. "api/geometry/post"
     * @apiParam {string} endpoint_data Json data that should be send to the endpoint when called.
     *
     * @apiSuccess {int} execution_task_id Unique task identifier used to identify this execution task.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function AddToBatch(
        int $batch_id,
        int $batch_group,
        string $call_id,
        string $endpoint,
        string $endpoint_data
    ): string {
        $endpoint = preg_replace("/\A[\/]?api\//", "", $endpoint);
        
        Database::GetInstance()->query("INSERT INTO api_batch_task (
			api_batch_task_batch_id, 
			api_batch_task_group, 
			api_batch_task_reference_identifier, 
			api_batch_task_api_endpoint, 
			api_batch_task_api_endpoint_data)
			VALUES (?, ?, ?, ?, ?)", array(
                $batch_id,
                $batch_group,
                $call_id,
                $endpoint,
                $endpoint_data));
        
        return $call_id;
    }

    /**
     * @apiGroup Batch
     * @throws Exception
     * @api {POST} /batch/executebatch ExecuteBatch
     * @apiDescription execute batch
     *
     * @apiParam {int} batch_id Batch ID to execute.
     * @apiParam {bool} async Execute this batch asynchronous. Results of the executed batch will be communicated
     *   through the websocket connection.
     *
     * @apiSuccess {object} results Results of the executed batch. JSON object containing client-specified call_id
     *   (if non-empty) and payload which is the result of the call.
     * When failed this object contains failed_task_id which references the execution_task_id returned in the
     *   AddToBatch.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ExecuteBatch(int $batch_id, bool $async = false)/*: array|string // <-- php 8 */
    {
        if ($async) {
            $data = Database::GetInstance()->query("SELECT api_batch_task_id, 
                    api_batch_task_reference_identifier, 
                    api_batch_task_api_endpoint, 
                    api_batch_task_api_endpoint_data 
                FROM api_batch_task 
                WHERE api_batch_task_batch_id = ? 
                ORDER BY api_batch_task_group", array($batch_id));
            if (empty($data)) {
                throw new Exception("Tried to execute an empty batch");
            }

            // queue it
            Database::GetInstance()->query(
                'UPDATE api_batch SET api_batch_state="Queued" WHERE api_batch_id = ?',
                array($batch_id)
            );

            // no results yet, will be sent later through websocket connection
            return '';
        }

        $batchResult = array("results" => array());
        $cachedResults = array(); //Results by call-id indexed;

        $data = Database::GetInstance()->query("SELECT api_batch_task_id, 
				api_batch_task_reference_identifier, 
				api_batch_task_api_endpoint, 
				api_batch_task_api_endpoint_data 
			FROM api_batch_task 
			WHERE api_batch_task_batch_id = ? 
			ORDER BY api_batch_task_group", array($batch_id));
        if (empty($data)) {
            throw new Exception("Tried to execute an empty batch");
        }

        foreach ($data as $task) {
            $endpoint = $task['api_batch_task_api_endpoint'];
            $callData = json_decode($task['api_batch_task_api_endpoint_data'], true);

            array_walk_recursive(
                $callData,
                function (&$value, $key, array $presentResults) {
                    self::fixupReferences($value, $key, $presentResults);
                },
                $cachedResults
            );

            $endpointData = Router::ParseEndpointString($endpoint);
            $taskResult = Router::ExecuteCall($endpointData['class'], $endpointData['method'], $callData, false);

            if ($taskResult['success'] == 0) {
                $batchResult['failed_task_id'] = $task['api_batch_task_id'];
                throw new Exception(
                    "ExecuteBatch failed. A subtask (".$task['api_batch_task_id'].") failed with \"".
                    $taskResult['message']."\""
                );
            } elseif (!empty($task['api_batch_task_reference_identifier'])) {
                $batchResult["results"][] = array(
                    "call_id" => $task['api_batch_task_reference_identifier'],
                    "payload" => $taskResult["payload"]);
                $cachedResults[$task['api_batch_task_reference_identifier']] = $taskResult["payload"];
            }
        }

        return $batchResult;
    }

    public function executeQueuedBatchesFor(int $teamId, int $userId): PromiseInterface
    {
        // get batches to execute
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
            $qb
                ->select('b.api_batch_id')
                ->from('api_batch', 'b')
                ->where(
                    $qb->expr()->and(
                        'b.api_batch_state = "Queued"',
                        'b.api_batch_country_id = ' . $qb->createPositionalParameter($teamId),
                        'b.api_batch_user_id = ' . $qb->createPositionalParameter($userId)
                    )
                )
        )
        ->then(function (Result $result) {
            $batchIds = collect($result->fetchAllRows() ?: [])
                ->keyBy('api_batch_id')
                ->keys()
                ->all();
            return $this->executeQueuedBatches($batchIds);
        });
    }

    public function executeQueuedBatches(array $batchIds): PromiseInterface
    {
        if (empty($batchIds)) {
            return resolveOnFutureTick(new Deferred(), [])->promise();
        }

        // get batch tasks to execute
        foreach ($batchIds as $batchId) {
            $this->cachedBatchResults[$batchId] = [];
        }
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        return $this->getAsyncDatabase()->query(
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
                        $qb->expr()->in('b.api_batch_id', $batchIds)
                    )
                )
        )
        ->then(function (Result $result) use ($batchIds) {
            $deferred = new Deferred();
            // first set the state to "Executing" before continuing
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            $this->getAsyncDatabase()->query(
                $qb
                    ->update('api_batch')
                    ->set('api_batch_state', $qb->createPositionalParameter('Executing'))
                    ->where($qb->expr()->in('api_batch_id', $batchIds))
            )
            ->done(
                function (Result $dummy) use ($deferred, $result) {
                    // just pass the original result.
                    $deferred->resolve($result);
                },
                function () use ($deferred, $batchIds) {
                    $deferred->reject('Could not set to status "Executing" for batch ids: ' . implode(', ', $batchIds));
                }
            );
            return $deferred->promise();
        })
        ->then(function (Result $result) use (&$promises, $batchIds) {
            $batchTasksContainer = collect($result->fetchAllRows() ?: [])
                ->groupBy('api_batch_task_batch_id')
                ->map(function (Collection $batchTasks, $batchId) {
                     return $batchTasks->sortBy('api_batch_task_group')->all();
                })
                ->all();

            $promises = [];
            /** @var array $batchTasks */
            foreach ($batchTasksContainer as $batchId => $batchTasks) {
                if (empty($batchTasks)) {
                    continue;
                }
                foreach ($batchTasks as $task) {
                    $callData = json_decode($task['api_batch_task_api_endpoint_data'], true);
                    $endpoint = $task['api_batch_task_api_endpoint'];

                    // create ObjectMethod and inject game session id, and async database into the instance.
                    $objectMethod = Router::createObjectMethodFromEndpoint($endpoint);
                    /** @var Base $instance */
                    $instance = $objectMethod->getInstance();
                    $this->asyncDataTransferTo($instance);

                    $promises[$batchId.'-'.$task['api_batch_task_reference_identifier']] = Router::executeCallAsync(
                        $objectMethod,
                        $callData,
                        function (array &$callData) use ($batchId) {
                            // fix references in call data using batch cache results
                            array_walk_recursive(
                                $callData,
                                function (&$value, $key, array $presentResults) {
                                    self::fixupReferences($value, $key, $presentResults);
                                },
                                $this->cachedBatchResults[$batchId]
                            );
                        },
                        function (&$payload) use ($batchId, $task) {
                            // fill batch cache results
                            $this->cachedBatchResults[$batchId][$task['api_batch_task_reference_identifier']] =
                                $payload;
                        }
                    );
                }
            }
            return chain($promises)
                ->then(function (array $taskResultsContainer) use ($batchIds) {
                    $batchResult = [];
                    foreach ($taskResultsContainer as $batchTaskIdentifier => $taskResult) {
                        list($batchId, $taskId) = explode('-', $batchTaskIdentifier);

                        // run async query to set batches to success, no need to wait for the result.
                        $qb = $this->getAsyncDatabase()->createQueryBuilder();
                        $this->getAsyncDatabase()->query(
                            $qb
                                ->update('api_batch')
                                ->set('api_batch_state', $qb->createPositionalParameter('Success'))
                                ->where($qb->expr()->in('api_batch_id', $batchIds))
                        );

                        $batchResult[$batchId]['results'][] = [
                            'call_id' => $taskId,
                            'payload' => $taskResult['payload']
                        ];
                    }
                    return $batchResult;
                })
                ->otherwise(function ($reason) use ($batchIds) {
                    // run async query to set batches to failed, no need to wait for the result.
                    $qb = $this->getAsyncDatabase()->createQueryBuilder();
                    $this->getAsyncDatabase()->query(
                        $qb
                            ->update('api_batch')
                            ->set('api_batch_state', $qb->createPositionalParameter('Failed'))
                            ->where($qb->expr()->in('api_batch_id', $batchIds))
                    );
                    // Propagate by returning rejection
                    return reject($reason);
                })
                ->always(function () use ($batchIds) {
                    foreach ($batchIds as $batchId) {
                        // clean up batch cache results
                        unset($this->cachedBatchResults[$batchId]);
                    }
                });
        });
    }

    /**
     * @param mixed $value
     * @param mixed $key
     * @param array $presentResults
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function fixupReferences(&$value, $key, array $presentResults): void
    {
        $refSpecifierLength = strlen(self::REFERENCE_SPECIFIER);

        if (!is_string($value) ||
            substr($value, 0, $refSpecifierLength) != self::REFERENCE_SPECIFIER) {
            return;
        }

        $firstArrayAccessor = strstr($value, '[');
        $childSpecifiers = null;
        if ($firstArrayAccessor !== false) {
            $firstArrayAccessor -= $refSpecifierLength;
            $matches = preg_match_all("/\[(?<Accessor>[a-zA-Z0-9]+)\]*/", $value);
            $childSpecifiers = $matches["Accessor"];
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
