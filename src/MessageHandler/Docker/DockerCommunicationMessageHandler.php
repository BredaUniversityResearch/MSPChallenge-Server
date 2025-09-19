<?php

namespace App\MessageHandler\Docker;

use App\Domain\Services\ConnectionManager;
use App\Domain\Services\ImmersiveSessionService;
use App\Entity\SessionAPI\ImmersiveSession;
use App\Message\Docker\CreateImmersiveSessionContainerMessage;
use App\Message\Docker\DockerCommunicationMessageBase;
use App\Message\Docker\ImmersiveSessionContainerMessageBase;
use App\Message\Docker\RemoveImmersiveSessionContainerMessage;
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
    public function __invoke(
        DockerCommunicationMessageBase $message
    ): void {
        if ($message instanceof ImmersiveSessionContainerMessageBase) {
            $this->handleImmersiveSessionContainer($message);
            return;
        }
        $this->dockerLogger->error('Unknown message type: '.get_class($message));
    }

    /**
     * @throws Exception
     */
    public function handleImmersiveSessionContainer(
        ImmersiveSessionContainerMessageBase $message
    ): void {
        $immersiveSessionId = $message->getImmersiveSessionId();
        $em = $this->connectionManager->getGameSessionEntityManager($message->getGameSessionId());
        $immersiveSession = $em->find(ImmersiveSession::class, $immersiveSessionId);
        switch (get_class($message)) {
            case CreateImmersiveSessionContainerMessage::class:
                if (null === $immersiveSession) {
                    $this->dockerLogger->warning('Immersive session not found: ' . $immersiveSessionId);
                    return;
                }
                $this->immersiveSessionService->createImmersiveSessionContainer(
                    $immersiveSession,
                    $message->getGameSessionId()
                );
                break;
            case RemoveImmersiveSessionContainerMessage::class:
                if (null !== $immersiveSession) {
                    $this->immersiveSessionService->removeImmersiveSessionConnection(
                        $immersiveSession,
                        $message->getGameSessionId()
                    );
                }
                $this->immersiveSessionService->removeImmersiveSessionContainer($message->getDockerContainerId());
                break;
            default:
                $this->dockerLogger->error('Unknown message type: '.get_class($message));
                break;
        }
    }
}
