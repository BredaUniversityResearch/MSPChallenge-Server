<?php

namespace App\EventListener;

use App\Domain\Services\ConnectionManager;
use App\Message\Watchdog\Message\WatchdogMessageBase;
use App\src\Entity\SessionAPI\Watchdog;
use App\src\Repository\SessionAPI\WatchdogRepository;
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
        if (!($message instanceof WatchdogMessageBase)) {
            return;
        }

        $em = $this->connectionManager->getGameSessionEntityManager($message->getGameSessionId());
        /** @var WatchdogRepository $repo */
        $repo = $em->getRepository(Watchdog::class);
        $repo->removeUnresponsiveWatchdogs();
        if ($event->getThrowable()->getCode() != Response::HTTP_METHOD_NOT_ALLOWED) {
            return;
        }
        // the watchdog does not want to join this session, remove it
        $em->remove($repo->find($message->getWatchdogId()));
        $em->flush();
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
