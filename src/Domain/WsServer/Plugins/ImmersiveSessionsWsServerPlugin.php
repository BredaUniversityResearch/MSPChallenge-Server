<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\Context;
use App\Domain\Common\EntityEnums\ImmersiveSessionStatus;
use App\Domain\Common\EntityEnums\ImmersiveSessionTypeID;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\WsServer\ClientDisconnectedException;
use App\Domain\WsServer\ClientHeaderKeys;
use App\Entity\SessionAPI\ImmersiveSession;
use App\Entity\SessionAPI\DockerConnection;
use App\Entity\SessionAPI\ImmersiveSessionStatusResponse;
use App\Security\BearerTokenValidator;
use Drift\DBAL\Result;
use Exception;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function App\tpf;
use function React\Promise\all;
use function React\Promise\reject;

class ImmersiveSessionsWsServerPlugin extends Plugin
{
    public static function getDefaultMinIntervalSec(): float
    {
        return 2;
    }

    public function __construct(?float $minIntervalSec = null)
    {
        parent::__construct('immersive_sessions', $minIntervalSec);
    }

    protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction
    {
        return tpf(function (?Context $context) {
            return $this->update($context)
                ->then(function (array $payloadContainer) {
                    $this->addOutput(
                        'just finished "latest" for connections: ' . implode(', ', array_keys($payloadContainer)),
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                    $payloadContainer = array_filter($payloadContainer);
                    if (empty($payloadContainer)) {
                        return;
                    }
                    $this->addOutput(json_encode($payloadContainer));
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
    private function updateForClient(int $connResourceId, array $clientInfo): PromiseInterface
    {
        $newUpdateTime = microtime(true);
        $lastUpdateTime = $clientInfo['last_update_time_is'] ?? 0;
        $db = $this->getAsyncDatabase($connResourceId);
        $qb = $db->createQueryBuilder();
        return $db->query(
            $qb
                ->select('s.*', 'c.docker_api_id', 'c.port', 'c.docker_container_id')
                ->from('immersive_session', 's')
                ->leftJoin('s', 'docker_connection', 'c', 'c.id = s.docker_connection_id')
                ->where($qb->expr()->gt(
                    's.updated_at',
                    $qb->createPositionalParameter(date('Y-m-d H:i:s', (int)$lastUpdateTime))
                ))
                ->andWhere($qb->expr()->lt(
                    's.updated_at',
                    $qb->createPositionalParameter(date('Y-m-d H:i:s', (int)$newUpdateTime))
                ))
        )
        ->then(function (Result $result) use ($connResourceId, $newUpdateTime) {
            $rows = ($result->fetchAllRows() ?? []) ?: [];
            if (empty($rows)) {
                return [];
            }
            if (null === $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)) {
                // disconnected while running this async code, nothing was sent
                $e = new ClientDisconnectedException();
                $e->setConnResourceId($connResourceId);
                throw $e;
            }

            $payload = array_map(fn($is) => $this->createEntityFromAssociative($is), $rows);
            $data = $this->getSerializer()->serialize(
                [
                    'header_type' => 'ImmersiveSessions/Update',
                    'header_data' => null,
                    'success' => true,
                    'message' => null,
                    'payload' => $payload
                ],
                'json',
                ['groups' => ['read']]
            );
            $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)->send($data);
            $this->getClientConnectionResourceManager()->setClientInfo(
                $connResourceId,
                'last_update_time_is',
                $newUpdateTime
            );
            return $payload;
        });
    }

    /**
     * @throws Exception
     */
    private function createEntityFromAssociative(array $data): ImmersiveSession
    {
        $conn = null;
        if (isset($data['port']) && isset($data['docker_api_id'])) {
            $conn = new DockerConnection();
            $conn
                ->setPort($data['port'])
                ->setDockerApiID($data['docker_api_id'])
                ->setDockerContainerID($data['docker_container_id'] ?? null);
        }
        $is = new ImmersiveSession();
        $is
            ->setId($data['id'])
            ->setName($data['name'])
            ->setType(ImmersiveSessionTypeID::from($data['type']))
            ->setMonth($data['month'])
            ->setStatus(ImmersiveSessionStatus::from($data['status']))
            ->setStatusResponse($this->getSerializer()->deserialize(
                $data['status_response'],
                ImmersiveSessionStatusResponse::class,
                'json'
            ))
            ->setBottomLeftX($data['bottom_left_x'])
            ->setBottomLeftY($data['bottom_left_y'])
            ->setTopRightX($data['top_right_x'])
            ->setTopRightY($data['top_right_y'])
            ->setData(json_decode($data['data'], true))
            ->setConnection($conn);
        return $is;
    }

    /**
     * @throws Exception
     */
    private function update(?Context $context): Promise
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
                if (!(new BearerTokenValidator())->setTokenFromHeader(
                    $this->getClientConnectionResourceManager()->getClientHeaders($connResourceId)[
                        ClientHeaderKeys::HEADER_KEY_MSP_API_TOKEN
                    ]
                )->validate()
                ) {
                    $this->addOutput(
                        'Client\'s token has  expired, let the client re-connect with a new token'
                    );
                    $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)->close();
                    continue;
                }
                $promises[$connResourceId] = $this->updateForClient($connResourceId, $clientInfo);
            }
        }
        /** @var PromiseInterface&Promise $promise */
        $promise = all($promises);
        return $promise;
    }

    /**
     * @throws Exception
     */
    private function getAsyncDatabase(int $connResourceId): \Drift\DBAL\Connection
    {
        $clientHeaders = $this->getClientConnectionResourceManager()->getClientHeaders($connResourceId);
        $gameSessionId = $clientHeaders[ClientHeaderKeys::HEADER_KEY_GAME_SESSION_ID];
        return $this->getServerManager()->getGameSessionDbConnection($gameSessionId);
    }
}
