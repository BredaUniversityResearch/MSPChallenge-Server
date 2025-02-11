<?php

namespace App\Domain\WsServer\Console;

use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\WsServerEventDispatcherInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

abstract class TableViewBase extends ViewBase
{
    // space required for height, table header, footer and cursor itself
    const HEIGHT_RESERVED_LINES = 5;

    protected ?int $terminalHeight = null;
    protected Table $table;
    protected string $startDateTime;

    public function __construct(
        ConsoleOutput $output
    ) {
        parent::__construct($output);
        $this->table = new Table($output);
        $this->startDateTime = date('j M H:i:s');
    }

    public function setTerminalHeight(?int $terminalHeight): void
    {
        $this->terminalHeight = $terminalHeight;
    }

    protected function postponeRender(NameAwareEvent $event): bool
    {
        // we only render the table view on the stats update event.
        return $event->getEventName() != WsServerEventDispatcherInterface::EVENT_ON_STATS_UPDATE;
    }

    protected function render(NameAwareEvent $event): void
    {
        $this->output->write("\033\143"); // resets the terminal, effectively clearing the screen
        $this->table->render();
    }
}
