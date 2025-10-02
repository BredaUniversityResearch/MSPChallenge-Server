<?php

namespace App\MessageHandler\Docker;

use App\Domain\Common\EntityEnums\ImmersiveSessionStatus;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\ImmersiveSessionService;
use App\Entity\ServerManager\DockerApi;
use App\Entity\ServerManager\GameList;
use App\Entity\SessionAPI\DockerConnection;
use App\Entity\SessionAPI\ImmersiveSession;
use App\Message\Docker\CreateImmersiveSessionConnectionMessage;
use App\Message\Docker\DockerCommunicationMessageBase;
use App\Message\Docker\ImmersiveSessionConnectionMessageBase;
use App\Message\Docker\InspectDockerConnectionsMessage;
use App\Message\Docker\RemoveImmersiveSessionConnectionMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DockerCommunicationMessageHandler
{
    public function __construct(
        private ConnectionManager $connectionManager,
        private ImmersiveSessionService $immersiveSessionService,
        private LoggerInterface $dockerLogger
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(DockerCommunicationMessageBase $message): void
    {
        if ($message instanceof ImmersiveSessionConnectionMessageBase) {
            $this->handleImmersiveSessionContainer($message);
            return;
        }
        if (get_class($message) == InspectDockerConnectionsMessage::class) {
            $this->inspectDockerConnections();
            return;
        }
        $this->dockerLogger->error('Unknown message type: '.get_class($message));
    }

     /**
     * Extracts the session ID from the Docker container environment variables.
     */
    private function extractSessionIdFromEnv(array $env): int
    {
        foreach ($env as $envVar) {
            if (preg_match('/^MSP_CHALLENGE_SESSION_ID=(\d+)$/', $envVar, $matches)) {
                return (int)$matches[1];
            }
        }
        return 0;
    }

    /**
     * @throws Exception
     */
    private function findImmersiveSession(
        int $gameSessionId,
        string $containerId,
        array &$context
    ) : ?ImmersiveSession {
        foreach ($context[$gameSessionId]['sessions'] as $session) {
            if ($session->getConnection()?->getDockerContainerID() === $containerId) {
                $context[$gameSessionId]['connections'][$session->getId()] = $session->getConnection();
                return $session;
            }
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function handleImmersiveSessionContainer(ImmersiveSessionConnectionMessageBase $message): void
    {
        $immersiveSessionId = $message->getImmersiveSessionId();
        $em = $this->connectionManager->getGameSessionEntityManager($message->getGameSessionId());
        $immersiveSession = $em->find(ImmersiveSession::class, $immersiveSessionId);
        switch (get_class($message)) {
            case CreateImmersiveSessionConnectionMessage::class: // starting
                if (null === $immersiveSession) {
                    $this->dockerLogger->warning('Immersive session not found: ' . $immersiveSessionId);
                    return;
                }
                try {
                    $this->immersiveSessionService->createImmersiveSessionContainer(
                        $this->immersiveSessionService->pickDockerApi(),
                        $immersiveSession,
                        $message->getGameSessionId()
                    );
                } catch (Exception $e) {
                    $immersiveSession->setStatusResponse([
                        'message' => 'Create immersive session container failed, will retry',
                        'error' => $e->getMessage()
                    ]);
                    $em->persist($immersiveSession);
                    $this->dockerLogger->warning(
                        'Create immersive session container failed, will retry: '.$e->getMessage()
                    );
                    $em->flush();
                    throw $e; // trigger retry or stop retrying
                }
                break;
            case RemoveImmersiveSessionConnectionMessage::class:
                if (null !== $immersiveSession) {
                    $this->immersiveSessionService->removeImmersiveSessionConnection(
                        $immersiveSession,
                        $message->getGameSessionId()
                    );
                }
                $this->immersiveSessionService->removeImmersiveSessionContainer(
                    $immersiveSession->getConnection()->getDockerApi(),
                    $message->getDockerContainerId()
                );
                break;

            default:
                $this->dockerLogger->error('Unknown message type: '.get_class($message));
                break;
        }
    }

    /**
     * @return array<int, array{sessions: array<int, ImmersiveSession>}>
     * @throws Exception
     */
    public function createInspectDockerConnectionsContext(): array
    {
        $context = [];
        // pre-cache all sessions for all game sessions
        $em = $this->connectionManager->getServerManagerEntityManager();
        /** @var GameList[] $gameLists */
        $gameLists = $em->getRepository(GameList::class)->createQueryBuilder('g')
            ->where('g.sessionState = :state')
            ->setParameter('state', 'healthy')
            ->getQuery()
            ->getResult();
        foreach ($gameLists as $gameList) {
            $gameSessionId = $gameList->getId();
            $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
            $context[$gameSessionId]['sessions'] = collect($em->getRepository(ImmersiveSession::class)
                ->findAll())->keyBy(
                    fn(ImmersiveSession $session) => $session->getId()
                )
                ->all();
        }
        return $context;
    }

    /**
     * @throws Exception
     */
    public function setImmersiveSessionsToUnresponsive(DockerApi $dockerApi, array &$context): void
    {
        foreach ($context as $gameSessionId => $gameSession) {
            $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
            foreach ($gameSession['sessions'] as $session) {
                // only running sessions can become unresponsive
                if ($session->getStatus() != ImmersiveSessionStatus::RUNNING) {
                    continue;
                }
                /** @var DockerConnection $connection */
                $connection = $session->getConnection();
                if ($connection->getDockerApiID() === $dockerApi->getId()) {
                    $connection->setVerified(true); // avoid removal of session
                    $session->setStatus(ImmersiveSessionStatus::UNRESPONSIVE);
                    $em->persist($session);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    public function setImmersiveSessionToUnresponsive(DockerApi $dockerApi, string $containerId, array &$context): void
    {
        foreach ($context as $gameSessionId => $gameSession) {
            $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
            foreach ($gameSession['sessions'] as $session) {
                // only running sessions can become unresponsive
                if ($session->getStatus() != ImmersiveSessionStatus::RUNNING) {
                    continue;
                }
                /** @var DockerConnection $connection */
                $connection = $session->getConnection();
                if ($connection->getDockerApiID() === $dockerApi->getId() &&
                    $connection->getDockerContainerID() === $containerId) {
                    $connection->setVerified(true); // avoid removal of session
                    $session->setStatus(ImmersiveSessionStatus::UNRESPONSIVE);
                    $em->persist($session);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    public function inspectDockerConnections(): void
    {
        $dockerApis = $this->connectionManager->getServerManagerEntityManager()
            ->getRepository(DockerApi::class)->findAll();
        $context = $this->createInspectDockerConnectionsContext();
        foreach ($dockerApis as $dockerApi) {
            try {
                $containers = $this->immersiveSessionService->listImmersiveSessionContainers($dockerApi);
            } catch (Exception $e) {
                $this->dockerLogger->error('List immersive sessions failed: '.$e->getMessage());
                // for all sessions having connections for this docker api set its status to unresponsive
                $this->setImmersiveSessionsToUnresponsive($dockerApi, $context);
                continue;
            }
            $this->dockerLogger->info('Found '.count($containers).' immersive sessions');
            foreach ($containers as $container) {
                try {
                    $inspectData = $this->immersiveSessionService->inspectImmersiveSessionContainer(
                        $dockerApi,
                        $container['Id']
                    );
                } catch (Exception $e) {
                    $this->dockerLogger->error('Inspect immersive session failed: '.$e->getMessage());
                    // for the session having this connection set its status to unresponsive
                    $this->setImmersiveSessionToUnresponsive($dockerApi, $container['Id'], $context);
                    continue;
                }
                $gameSessionId = $this->extractSessionIdFromEnv($inspectData['Config']['Env']);
                $this->dockerLogger->info('Inspect immersive session container '.$container['Id'].
                    ' for game session #'.$gameSessionId.
                    ' status: '.$inspectData['State']['Status'].
                    ($inspectData['State']['Status'] == 'running' ?
                        ', health: '.($inspectData['State']['Health']['Status'] ?? 'none') : ''
                    )
                );
                if (null === $session = $this->findImmersiveSession(
                    $gameSessionId,
                    $container['Id'],
                    $context
                )) {
                    $this->dockerLogger->warning('Orphaned container found: '.$container['Id'].', removing it');
                    try {
                        // orphaned container, remove it
                        $this->immersiveSessionService->removeImmersiveSessionContainer(
                            $dockerApi,
                            $container['Id']
                        );
                    } catch (Exception $e) {
                        $this->dockerLogger->error(
                            'Failed to remove orphaned container: '.$container['Id'].': '.$e->getMessage()
                        );
                    }
                    continue;
                }
                $this->dockerLogger->info('Found immersive session #'.$session->getId());
                $session->getConnection()->setVerified(true);

                $status = match ($inspectData['State']['Status']) {
                    'created', 'restarting' => ImmersiveSessionStatus::STARTING,
                    'running' => ImmersiveSessionStatus::RUNNING,
                    'exited', 'paused' => ImmersiveSessionStatus::STOPPED,
                    default => ImmersiveSessionStatus::REMOVED // "removing" "dead"
                };
                if ($status == ImmersiveSessionStatus::RUNNING) {
                    $status = match ($inspectData['State']['Health']['Status'] ?? 'none') {
                        'starting' => ImmersiveSessionStatus::STARTING,
                        'healthy' => ImmersiveSessionStatus::RUNNING,
                        default => ImmersiveSessionStatus::UNRESPONSIVE // "none" "unhealthy"
                    };
                    $session->setStatusResponse(
                        ($inspectData['State']['Health']['Log'] ?? []) ?:
                            ['message' => 'Health check is unavailable']
                    );
                }
                $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
                $session
                    ->setStatus($status);
                $em->persist($session);

                if ($status == ImmersiveSessionStatus::STARTING || $status == ImmersiveSessionStatus::RUNNING) {
                    // everything is fine
                    continue;
                }
                if ($status == ImmersiveSessionStatus::REMOVED ||
                    // for now, we also remove session of which the container is stopped
                    $status == ImmersiveSessionStatus::STOPPED) {
                    $this->dockerLogger->warning('Immersive session connection lost, removing it: '.$session->getId());
                    try {
                        // discontinued container, remove it
                        $this->immersiveSessionService->removeImmersiveSessionContainer(
                            $dockerApi,
                            $container['Id']
                        );
                    } catch (Exception $e) {
                        $this->dockerLogger->error(
                            'Failed to remove discontinued container: '.$container['Id'].': '.$e->getMessage()
                        );
                    }
                    $session
                        ->setStatus(ImmersiveSessionStatus::REMOVED);
                    $em->persist($session);
                }
            }
        }
        // remove all sessions that are not verified (no container found)
        $this->removeUnverifiedImmersiveSessions($context);
        // flush all changes at once
        foreach (array_keys($context) as $gameSessionId) {
            $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
            $em->flush();
        }
        // do another flush to actually remove all immersive sessions set to status "removed"
        foreach ($context as $gameSessionId => $gameSession) {
            $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
            foreach ($gameSession['sessions'] as $session) {
                if ($session->getStatus() == ImmersiveSessionStatus::REMOVED) {
                    $em->remove($session);
                }
            }
            $em->flush();
        }
    }

    /**
     * @throws Exception
     */
    public function removeUnverifiedImmersiveSessions(array &$context): void
    {
        foreach ($context as $gameSessionId => $gameSession) {
            foreach ($gameSession['sessions'] as $sessionId => $session) {
                $createdAt = $session->getCreatedAt();
                $updatedAt = $session->getUpdatedAt();
                $bootupTime = (new \DateTime())->getTimestamp() - $updatedAt->getTimestamp();
                if (
                    // leave sessions in starting state - allow it to connect
                    $session->getStatus() == ImmersiveSessionStatus::STARTING &&
                    // but in-case it was not updated by the message handler, remove it after 30 seconds
                    !(
                        $createdAt instanceof \DateTimeInterface &&
                        $updatedAt instanceof \DateTimeInterface &&
                        $createdAt == $updatedAt &&
                        $bootupTime > 30
                    )
                ) {
                    continue;
                }
                if ($session->getConnection() === null || !$session->getConnection()->isVerified()) {
                    $this->dockerLogger->warning(
                        'Immersive session connection lost, removing it. Session: '.$sessionId.
                        ', verified: '.($session->getConnection()->isVerified() ? 'yes' : 'no').
                        ', boot-up time: '.$bootupTime
                    );
                    $em = $this->connectionManager->getGameSessionEntityManager($gameSessionId);
                    $em->remove($session);
                }
            }
        }
    }
}
