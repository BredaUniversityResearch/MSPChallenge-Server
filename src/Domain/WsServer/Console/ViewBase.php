<?php

namespace App\Domain\WsServer\Console;

use App\Domain\Common\Stopwatch\Stopwatch;
use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientConnectionResourceManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Yaml\Yaml;

abstract class ViewBase implements ViewInterface
{
    protected ConsoleOutput $output;
    protected ClientConnectionResourceManagerInterface $clientConnectionResourceManager;
    protected ?Stopwatch $stopwatch;
    protected bool $renderingEnabled = false;

    public function __construct(ConsoleOutput $output)
    {
        $this->output = $output;
    }

    public function setClientConnectionResourceManager(
        ClientConnectionResourceManagerInterface $clientConnectionResourceManager
    ): void {
        $this->clientConnectionResourceManager = $clientConnectionResourceManager;
    }

    public function setStopwatch(?Stopwatch $stopwatch): void
    {
        $this->stopwatch = $stopwatch;
    }

    protected function outputEvent(NameAwareEvent $event): void
    {
        $this->output->writeln(Yaml::dump($event->toArray()));
    }

    public function isRenderingEnabled(): bool
    {
        return $this->renderingEnabled;
    }

    public function setRenderingEnabled(bool $enabled): void
    {
        $this->renderingEnabled = $enabled;
    }

    protected function postponeRender(NameAwareEvent $event): bool
    {
        return false;
    }
    abstract protected function process(NameAwareEvent $event): void;
    abstract protected function render(NameAwareEvent $event): void;

    public function notifyWsServerDataChange(NameAwareEvent $event): void
    {
        $this->process($event);
        if ($this->postponeRender($event) || !$this->renderingEnabled) {
            return;
        }
        $this->render($event);
    }
}
