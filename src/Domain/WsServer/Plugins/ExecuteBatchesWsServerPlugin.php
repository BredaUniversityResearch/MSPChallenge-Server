<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\Context;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\API\v1\Batch;
use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientDisconnectedException;
use App\Domain\WsServer\ClientHeaderKeys;
use App\Domain\WsServer\ExecuteBatchRejection;
use App\Domain\WsServer\WsServerEventDispatcherInterface;
use Doctrine\DBAL\ArrayParameterType;
use Drift\DBAL\Result;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function App\parallel;
use function App\tpf;
use function React\Promise\all;
use function React\Promise\reject;

class ExecuteBatchesWsServerPlugin extends Plugin
{
    private bool $firstStart = true;

    /**
     * @var Batch[]
     */
    private array $batchesInstances = [];

    public static function getDefaultMinIntervalSec(): float
    {
        return 1;
    }

    public function __construct(?float $minIntervalSec = null)
    {
        parent::__construct('executeBatches', $minIntervalSec);
    }

    protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction
    {
        return tpf(function (Context $context) use ($executionId) {
            if ($this->firstStart) {
                // allow clients to connect in the next 10 sec. todo: can we improve this?
                $this->getLoop()->addTimer(10, function () {
                    return $this->failExecutingBatchesOnStart();
                });
                $this->firstStart = false;
            }

            return $this->executeBatches($executionId, $context)
                ->then(function (array $clientToBatchResultContainer) {
                    $this->addOutput(
                        'just finished "executeBatches" for connections: ' .
                            implode(', ', array_keys($clientToBatchResultContainer)),
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                    $clientToBatchResultContainer = array_filter($clientToBatchResultContainer);
                    if (empty($clientToBatchResultContainer)) {
                        return;
                    }
                    $this->addOutput(json_encode($clientToBatchResultContainer));
                })
                ->otherwise(function ($reason) {
                    if ($reason instanceof ClientDisconnectedException) {
                        return null;
                    }
                    return reject($reason);
                });
        });
    }

    /**
     * @throws Exception
     */
    private function failExecutingBatchesOnStart(): PromiseInterface
    {
        return $this->getServerManager()->getGameSessionIds()
            ->then(function (Result $result) {
                $gameSessionIds = collect(($result->fetchAllRows() ?? []) ?: [])
                    ->keyBy('id')
                    ->map(function ($row) {
                        return $row['id'];
                    });
                $gameSessionId = $this->getGameSessionIdFilter();
                if ($gameSessionId != null) {
                    $gameSessionIds = $gameSessionIds->only($gameSessionId);
                }
                $gameSessionIds = $gameSessionIds->all(); // to raw array
                $toPromiseFunctions = [];
                foreach ($gameSessionIds as $gameSessionId) {
                    $toPromiseFunctions[] = tpf(function () use ($gameSessionId) {
                        return $this->failExecutingBatchesOnStartForGameSession($gameSessionId);
                    });
                }
                return parallel($toPromiseFunctions);
            });
    }

    /**
     * @throws Exception
     */
    private function executeBatches(string $executionId, Context $context): PromiseInterface
    {
        $clientInfoPerSessionContainer = $this->getClientConnectionResourceManager()
            ->getClientInfoPerSessionCollection();
        $gameSessionId = $this->getGameSessionIdFilter();
        if ($gameSessionId != null) {
            $clientInfoPerSessionContainer = $clientInfoPerSessionContainer->only($gameSessionId);
        }
        $promises = [];
        foreach ($clientInfoPerSessionContainer as $clientInfoContainer) {
            foreach ($clientInfoContainer as $connResourceId => $clientInfo) {
                $promises[$connResourceId] = $this->getBatch($connResourceId)
                    ->executeNextQueuedBatchFor(
                        $clientInfo['team_id'],
                        $clientInfo['user'],
                        $this->getWsServer()->getId(),
                        function () use ($executionId) {
                            $this->dispatch(
                                new NameAwareEvent(
                                    self::EVENT_PLUGIN_EXECUTION_ENABLE_PROBE,
                                    $this,
                                    [self::EVENT_ARG_EXECUTION_ID => $executionId]
                                ),
                                self::EVENT_PLUGIN_EXECUTION_ENABLE_PROBE,
                            );
                        }
                    )
                ->then(
                    function (array $batchResultContainer) use ($connResourceId) {
                        $this->addOutput(
                            'Created "executeBatches" payload for: ' . $connResourceId,
                            OutputInterface::VERBOSITY_VERY_VERBOSE
                        );
                        if (empty($batchResultContainer)) {
                            return [];
                        }
                        if (null === $this->getClientConnectionResourceManager()->getClientConnection(
                            $connResourceId
                        )) {
                            // disconnected while running this async code, nothing was sent
                            $this->addOutput('disconnected while running this async code, nothing was sent');
                            $e = new ClientDisconnectedException();
                            $e->setConnResourceId($connResourceId);
                            throw $e;
                        }

                        $batchGuid = key($batchResultContainer);
                        $batchResult = current($batchResultContainer);

                        $data = [
                            'header_type' => 'Batch/ExecuteBatch',
                            'header_data' => [
                                'batch_guid' => $batchGuid,
                            ],
                            'success' => true,
                            'message' => null,
                            'payload' => $batchResult
                        ];
                        PluginHelper::getInstance()->dump($connResourceId, $data);
                        $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)->sendAsJson(
                            $data
                        );

                        return $this->setBatchToCommunicated($connResourceId, $batchGuid, $batchResultContainer);
                    },
                    function ($rejection) use ($connResourceId) {
                        $reason = $rejection;
                        if ($rejection instanceof ExecuteBatchRejection) {
                            $batchGuid = $rejection->getBatchGuid();
                            $reason = $rejection->getReason();
                            $message = '';
                            if (is_string($reason)) {
                                $message = $reason;
                            }
                            while ($reason instanceof Throwable) {
                                $message .= $reason->getMessage().PHP_EOL.
                                    (
                                        // add the call-stack on dev/test environments
                                        ($_ENV['APP_ENV'] ?? 'prod') !== 'prod' ?
                                            $reason->getTraceAsString() . PHP_EOL : ''
                                    );
                                $reason = $reason->getPrevious();
                            }
                            $data = [
                                'header_type' => 'Batch/ExecuteBatch',
                                'header_data' => [
                                    'batch_guid' => $batchGuid,
                                ],
                                'success' => false,
                                'message' => $message ?: 'Unknown reason',
                                'payload' => null
                            ];
                            PluginHelper::getInstance()->dump($connResourceId, $data);
                            $conn = $this->getClientConnectionResourceManager()->getClientConnection($connResourceId);
                            $conn?->sendAsJson($data); // check if connection is alive
                            return $this->setBatchToCommunicated(
                                $connResourceId,
                                $batchGuid,
                                [] // do not propagate rejection, just resolve to empty batch results
                            );
                        }
                        return reject($reason);
                    }
                );
            }
        }
        return all($promises);
    }

    /**
     * @throws Exception
     */
    private function setBatchToCommunicated(int $connResourceId, string $batchGuid, $value = null): PromiseInterface
    {
        // set batch as "communicated"
        $deferred = new Deferred();
        $this->getBatch($connResourceId)->setCommunicated($batchGuid)
            ->done(
                function (/* Result $result */) use ($deferred, $value) {
                    $deferred->resolve($value);
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
    private function getBatch(int $connResourceId): Batch
    {
        $clientHeaders = $this->getClientConnectionResourceManager()->getClientHeaders($connResourceId);
        $gameSessionId = $clientHeaders[ClientHeaderKeys::HEADER_KEY_GAME_SESSION_ID];
        if (!array_key_exists($connResourceId, $this->batchesInstances)) {
            $batch = new Batch();
            $batch->setAsync(true);
            $batch->setGameSessionId($gameSessionId);
            $batch->setAsyncDatabase($this->getServerManager()->getGameSessionDbConnection($gameSessionId));
            $batch->setToken($clientHeaders[ClientHeaderKeys::HEADER_KEY_MSP_API_TOKEN]);
            $this->batchesInstances[$connResourceId] = $batch;
        }
        return $this->batchesInstances[$connResourceId];
    }

    public function onWsServerEventDispatched(NameAwareEvent $event): void
    {
        if ($event->getEventName() == WsServerEventDispatcherInterface::EVENT_ON_CLIENT_DISCONNECTED) {
            unset($this->batchesInstances[$event->getSubject()]);
        }
    }

    /**
     * @throws Exception
     */
    private function failExecutingBatchesOnStartForGameSession(int $gameSessionId): PromiseInterface
    {
        $connection = $this->getServerManager()->getGameSessionDbConnection($gameSessionId);
        $qb = $connection->createQueryBuilder();
        return $connection->query(
            $qb
                ->select('b.api_batch_guid', 'b.api_batch_user_id', 'b.api_batch_country_id')
                ->from('api_batch', 'b')
                ->where(
                    $qb->expr()->and(
                        'b.api_batch_state = "Executing"',
                        'b.api_batch_server_id = ' .
                        $qb->createPositionalParameter($this->getWsServer()->getId())
                    )
                )
        )
        ->then(function (Result $result) use ($connection) {
            $batches = collect(($result->fetchAllRows() ?? []) ?: [])
                ->keyBy('api_batch_guid')
                ->all();
            if (empty($batches)) {
                return [];
            }
            $qb = $connection->createQueryBuilder();
            return $connection->query(
                $qb
                    ->update('api_batch', 'b')
                    ->set('b.api_batch_state', $qb->createPositionalParameter('Failed'))
                    ->where($qb->expr()->in(
                        'b.api_batch_guid',
                        $qb->createPositionalParameter($batches, ArrayParameterType::STRING)
                    ))
            )
            ->then(function (/* Result $result */) use ($batches) {
                // find client connections matching these batches if any
                $clientInfoContainer = $this->getClientConnectionResourceManager()
                    ->getClientInfoContainer();

                $toPromiseFunctions = [];
                foreach ($clientInfoContainer as $connResourceId => $clientInfo) {
                    foreach ($batches as $batchId => $batch) {
                        if ($batch['api_batch_user_id'] != $clientInfo['user'] ||
                            $batch['api_batch_country_id'] != $clientInfo['team_id']
                        ) {
                            continue;
                        }
                        $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)
                            ->sendAsJson([
                                'header_type' => 'Batch/ExecuteBatch',
                                'header_data' => [
                                    'batch_id' => $batchId,
                                ],
                                'success' => false,
                                'message' => 'Unknown reason',
                                'payload' => null
                            ]);

                        $toPromiseFunctions[] = tpf(function () use ($connResourceId, $batchId) {
                            return $this->setBatchToCommunicated($connResourceId, $batchId, $batchId);
                        });
                    }
                }
                return parallel($toPromiseFunctions);
            });
        });
    }
}
