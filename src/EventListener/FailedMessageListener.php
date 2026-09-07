<?php

namespace App\EventListener;

use App\Domain\Services\ConnectionManager;
use App\Entity\SessionAPI\ImmersiveSession;
use App\Entity\SessionAPI\ImmersiveSessionStatusResponse;
use App\Message\Docker\CreateImmersiveSessionConnectionMessage;
use App\Message\Watchdog\Message\WatchdogMessageBase;
use App\Entity\SessionAPI\Watchdog;
use App\Repository\SessionAPI\WatchdogRepository;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

readonly class FailedMessageListener implements EventSubscriberInterface
{
    public function __construct(
        private ConnectionManager $connectionManager
    ) {
    }

    /**
     * @throws Exception
     */
    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();

        if ($message instanceof CreateImmersiveSessionConnectionMessage) {
            $immersiveSessionId = $message->getImmersiveSessionId();
            $em = $this->connectionManager->getGameSessionEntityManager($message->getGameSessionId());
            $immersiveSession = $em->find(ImmersiveSession::class, $immersiveSessionId);
            $immersiveSession->setStatusResponse(new ImmersiveSessionStatusResponse([
                'message' => 'Create immersive session container failed after max retries. Error: ' .
                    $event->getThrowable()->getMessage() . '. Removing session.'
            ]));
            $em->remove($immersiveSession);
        }

        if (!($message instanceof WatchdogMessageBase)) {
            return;
        }

        $em = $this->connectionManager->getGameSessionEntityManager($message->getGameSessionId());
        /** @var WatchdogRepository $repo */
        $repo = $em->getRepository(Watchdog::class);
        $repo->removeUnresponsiveWatchdogs();

        // Below if sail-safe but should actually already been handled directly in the WatchdogMessageHandler,
        //   but just in case, we will handle it here as well.
        if ($event->getThrowable()->getCode() == Response::HTTP_METHOD_NOT_ALLOWED) {
            // the watchdog does not want to join this session, remove it

            // Watchdog is configured with Gedmo\SoftDeleteable(hardDelete: false), so a normal
            // $em->remove() would always be intercepted by SoftDeleteableListener::onFlush() and
            // converted into an UPDATE (setting deletedAt) instead of an actual DELETE.
            // A DQL bulk DELETE bypasses the ORM lifecycle/onFlush listeners entirely, giving us
            // a genuine hard delete here regardless of the entity's soft-delete configuration.
            $watchdog = $repo->find($message->getWatchdogId());
            $em->createQuery(
                'DELETE FROM '.Watchdog::class.' w WHERE w.id = :id'
            )->setParameter('id', $watchdog->getId())->execute();
            $em->detach($watchdog);

            $em->flush();
        }
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => ['onMessageFailed', -256],
        ];
    }
}
