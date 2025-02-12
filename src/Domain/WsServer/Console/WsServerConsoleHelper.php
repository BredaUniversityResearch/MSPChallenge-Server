<?php

namespace App\Domain\WsServer\Console;

use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\WsServer;
use App\Domain\WsServer\WsServerEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WsServerConsoleHelper implements EventSubscriberInterface
{
    private WsServer $wsServer;

    /***
     * @var ViewInterface[] $views
     */
    private array $views = [];

    public function __construct(WsServer $wsServer)
    {
        $this->wsServer = $wsServer;
        $wsServer->addSubscriber($this);
    }

    public function registerView(ViewInterface $view): self
    {
        if (isset($this->views[$view->getName()])) {
            throw new \InvalidArgumentException('View with name ' . $view->getName() . ' already registered');
        }
        $view->setClientConnectionResourceManager($this->wsServer);
        $view->setStopwatch($this->wsServer->getStopwatch());
        $this->views[$view->getName()] = $view;
        current($this->views)->setRenderingEnabled(true);
        return $this;
    }

    public function unregisterView(ViewInterface $view): void
    {
        unset($this->views[$view->getName()]);
        current($this->views)->setRenderingEnabled(true);
    }

    public function nextView(): void
    {
        current($this->views)->setRenderingEnabled(false);
        $n = next($this->views);
        if (false === $n) {
            $n = reset($this->views);
        }
        $n->setRenderingEnabled(true);
    }

    public function notifyWsServerDataChange(NameAwareEvent $event): void
    {
        foreach ($this->views as $view) {
            $view->notifyWsServerDataChange($event);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WsServerEventDispatcherInterface::EVENT_ON_CLIENT_CONNECTED => 'notifyWsServerDataChange',
            WsServerEventDispatcherInterface::EVENT_ON_CLIENT_DISCONNECTED => 'notifyWsServerDataChange',
            WsServerEventDispatcherInterface::EVENT_ON_CLIENT_ERROR => 'notifyWsServerDataChange',
            WsServerEventDispatcherInterface::EVENT_ON_CLIENT_MESSAGE_RECEIVED => 'notifyWsServerDataChange',
            WsServerEventDispatcherInterface::EVENT_ON_CLIENT_MESSAGE_SENT => 'notifyWsServerDataChange',
            WsServerEventDispatcherInterface::EVENT_ON_STATS_UPDATE => 'notifyWsServerDataChange',
            WsServerEventDispatcherInterface::EVENT_ON_NEXT_VIEW => 'nextView'
        ];
    }
}
