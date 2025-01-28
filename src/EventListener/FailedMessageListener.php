<?php

namespace App\EventListener;

use App\Domain\Services\ConnectionManager;
use App\Entity\Watchdog;
use App\Message\Watchdog\Message\WatchdogMessageBase;
use App\Repository\WatchdogRepository;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
