<?php

namespace App\Domain\API\v1;

use Exception;

class Batch extends Base
{
    private const REFERENCE_SPECIFIER = "!Ref:";

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
     * @apiSuccess {int} batch_id Batch ID used to identify this batch.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function StartBatch()
    {
        return Database::GetInstance()->query("INSERT INTO api_batch() VALUES ()", array(), true);
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
     * @api {POST} /batch/addtobatch AddToBatch
     * @apiDescription Starts a new batch
     *
     * @apiParam {int} batch_id Batch ID to execute.
     *
     * @apiSuccess {object} results Results of the executed batch. JSON object containing client-specified call_id
     *   (if non-empty) and payload which is the result of the call.
     * When failed this object contains failed_task_id which references the execution_task_id returned in the
     *   AddToBatch.
     * @ForceTransaction
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ExecuteBatch(int $batch_id): array
    {
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

        Database::GetInstance()->DBStartTransaction();
        foreach ($data as $task) {
            $endpoint = $task['api_batch_task_api_endpoint'];
            $callData = json_decode($task['api_batch_task_api_endpoint_data'], true);

            array_walk_recursive(
                $callData,
                function (&$value, $key, array $presentResults) {
                    self::FixupReferences($value, $key, $presentResults);
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
        Database::GetInstance()->DBCommitTransaction();

        return $batchResult;
    }

    /**
     * @param mixed $value
     * @param mixed $key
     * @param array $presentResults
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function FixupReferences(&$value, $key, array $presentResults): void
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
