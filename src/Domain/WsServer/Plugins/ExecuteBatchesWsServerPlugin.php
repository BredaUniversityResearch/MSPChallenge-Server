<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\API\v1\Batch;
use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientDisconnectedException;
use App\Domain\WsServer\ClientHeaderKeys;
use App\Domain\WsServer\ExecuteBatchRejection;
use App\Domain\WsServer\WsServerEventDispatcherInterface;
use Closure;
use Exception;
use React\Promise\PromiseInterface;
use Throwable;
use function React\Promise\all;
use function React\Promise\reject;

class ExecuteBatchesWsServerPlugin extends Plugin
{
    private const EXECUTE_BATCHES_MIN_INTERVAL_SEC = 1;

    /**
     * @var Batch[]
     */
    private array $batchesInstances = [];

    public function __construct()
    {
        parent::__construct('executeBatches', self::EXECUTE_BATCHES_MIN_INTERVAL_SEC);
    }

    protected function onCreatePromiseFunction(): Closure
    {
        return function () {
            return $this->executeBatches()
                ->then(function (array $clientToBatchResultContainer) {
                    $this->addDebugOutput(
                        'just finished "executeBatches" for connections: ' .
                        implode(', ', array_keys($clientToBatchResultContainer))
                    );
                    $clientToBatchResultContainer = array_filter($clientToBatchResultContainer);
                    $this->addDebugOutput(json_encode($clientToBatchResultContainer));
                })
                ->otherwise(function ($reason) {
                    if ($reason instanceof ClientDisconnectedException) {
                        return null;
                    }
                    return reject($reason);
                });
        };
    }

    /**
     * @throws Exception
     */
    private function executeBatches(): PromiseInterface
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
                $timeStart = microtime(true);
                $this->addDebugOutput('Starting "executeBatches" for: ' . $connResourceId);
                $promises[$connResourceId] = $this->getBatch($connResourceId)
                    ->executeNextQueuedBatchFor(
                        $clientInfo['team_id'],
                        $clientInfo['user']
                    )
                ->then(
                    function (array $batchResultContainer) use ($connResourceId, $timeStart, $clientInfo) {
                        $this->addDebugOutput('Created "executeBatches" payload for: ' . $connResourceId);
                        $this->getClientConnectionResourceManager()->addToMeasurementCollection(
                            $this->getName(),
                            $connResourceId,
                            microtime(true) - $timeStart
                        );
                        if (empty($batchResultContainer)) {
                            return [];
                        }
                        if (null === $this->getClientConnectionResourceManager()->getClientConnection(
                            $connResourceId
                        )) {
                            // disconnected while running this async code, nothing was sent
                            $this->addDebugOutput('disconnected while running this async code, nothing was sent');
                            $e = new ClientDisconnectedException();
                            $e->setConnResourceId($connResourceId);
                            throw $e;
                        }

                        $batchId = key($batchResultContainer);
                        $batchResult = current($batchResultContainer);

                        $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)->sendAsJson([
                            'header_type' => 'Batch/ExecuteBatch',
                            'header_data' => [
                                'batch_id' => $batchId,
                            ],
                            'success' => true,
                            'message' => null,
                            'payload' => $batchResult
                        ]);
                        return $batchResultContainer;
                    },
                    function ($rejection) use ($connResourceId) {
                        $reason = $rejection;
                        if ($rejection instanceof ExecuteBatchRejection) {
                            $batchId = $rejection->getBatchId();
                            $reason = $rejection->getReason();
                            $message = '';
                            if (is_string($reason)) {
                                $message = $reason;
                            }
                            while ($reason instanceof Throwable) {
                                $message .= $reason->getMessage() . PHP_EOL;
                                $reason = $reason->getPrevious();
                            }
                            $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)
                                ->sendAsJson([
                                    'header_type' => 'Batch/ExecuteBatch',
                                    'header_data' => [
                                        'batch_id' => $batchId,
                                    ],
                                    'success' => false,
                                    'message' => $message ?: 'Unknown reason',
                                    'payload' => null
                                ]);
                            return []; // do not propagate rejection, just resolve to empty batch results
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
}
