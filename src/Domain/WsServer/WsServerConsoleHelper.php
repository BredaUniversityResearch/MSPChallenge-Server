<?php

namespace App\Domain\WsServer;

use App\Domain\Event\NameAwareEvent;
use App\Domain\Helper\Util;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Terminal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WsServerConsoleHelper implements EventSubscriberInterface
{
    const WIDTH_RESERVED_CHARS_CLIENT = 11;
    const WIDTH_RESERVED_CHARS_TIME = 8;
    const WIDTH_RESERVED_CHARS_EVENT_NAME = 23;
    const WIDTH_RESERVED_CHARS_TABLE = 15;
    // excl. data column.
    const WIDTH_RESERVED_CHARS =
        self::WIDTH_RESERVED_CHARS_CLIENT +
        self::WIDTH_RESERVED_CHARS_TIME +
        self::WIDTH_RESERVED_CHARS_EVENT_NAME +
        self::WIDTH_RESERVED_CHARS_TABLE;

    // space required for height, table header, footer and cursor itself
    const HEIGHT_RESERVED_LINES = 5;

    private WsServer $wsServer;
    private Table $table;
    private array $tableInput = [];
    private ConsoleOutput $output;
    private ConsoleSectionOutput $section;
    private string $startDateTime;
    private ?int $terminalHeight = null;

    public function __construct(WsServer $wsServer, ConsoleOutput $output)
    {
        $this->wsServer = $wsServer;
        $wsServer->addSubscriber($this);
        $this->output = $output;
        $this->section = $output->section();
        $this->table = new Table($output);
        $this->startDateTime = date('j M H:i:s');
    }

    public function setTerminalHeight(?int $terminalHeight): void
    {
        $this->terminalHeight = $terminalHeight;
    }

    private function process(NameAwareEvent $event, int $allowedDataCharsWidth): void
    {
        if (null === $clientIds = $event->getSubject()) {
            return;
        }
        $clientDataContainer = $event->getArguments();
        // convert to array
        if (!is_array($clientIds)) {
            $clientDataContainer = [$clientIds => $clientDataContainer];
            $clientIds = [$clientIds];
        }
        foreach ($clientIds as $clientId) {
            if ($event->getEventName() == WsServer::EVENT_ON_CLIENT_DISCONNNECTED) {
                unset($this->tableInput[$clientId]);
                continue;
            }
            $clientData = $clientDataContainer[$clientId];
            $this->processTableInput($clientId, $event->getEventName(), $clientData, $allowedDataCharsWidth);
        }
    }

    private function getWsServerStats(): array
    {
        $wsServerStats = $this->wsServer->getStats();
        return collect($wsServerStats)
            ->groupBy(function ($item, $key) {
                if (false === $pos = strpos($key, '.')) {
                    return $key;
                }
                return substr($key, 0, $pos);
            })
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return Util::formatMilliseconds(($item ?? 0) * 1000);
                })->all();
            })
            ->map(function ($items, $key) {
                return $key . '=' . implode(' ', $items);
            })
            ->all();
    }

    public function notifyWsServerDataChange(NameAwareEvent $event): void
    {
        $terminal = new Terminal();
        $allowedDataCharsWidth = max(0, $terminal->getWidth() - self::WIDTH_RESERVED_CHARS);
        $this->process($event, $allowedDataCharsWidth);
        $wsServerStats = $this->getWsServerStats();
        $numClients = count($this->tableInput);
        $this->table
            ->setStyle('box')
            ->setHeaders(['client', 'time', 'event', 'data'])
            ->setRows($this->tableInput)
            ->setColumnWidth(0, self::WIDTH_RESERVED_CHARS_CLIENT)
            ->setColumnWidth(1, self::WIDTH_RESERVED_CHARS_TIME)
            ->setColumnWidth(2, self::WIDTH_RESERVED_CHARS_EVENT_NAME)
            ->setColumnWidth(3, $allowedDataCharsWidth)
            ->setHeaderTitle($numClients . ' clients connected / started: ' . $this->startDateTime . ' / ' .
                Util::getHumanReadableSize(memory_get_usage()))
            ->setFooterTitle(implode(' / ', $wsServerStats))
        ;
        if ($event->getEventName() == WsServer::EVENT_ON_STATS_UPDATE) {
            $this->output->write(sprintf("\033\143"));
            $this->table->render();
            return;
        }
    }

    private function processTableInput(
        int $clientId,
        string $eventName,
        array $clientData,
        int $allowedDataCharsWidth
    ): void {
        $numClients = count($this->tableInput);
        if ($eventName == WsServer::EVENT_ON_CLIENT_CONNECTED) {
            $numClients += 1;
        }
        $numClients = max(1, $numClients);
        $data = json_encode($clientData, JSON_PRETTY_PRINT);
        $terminal = new Terminal();
        $availableTotalLines = $this->terminalHeight ?? $terminal->getHeight();
        $availableTotalLines -= self::HEIGHT_RESERVED_LINES;
        $availableTotalLines = max(1, $availableTotalLines); // at least 1 line left to write
        $minLinesPerClient = floor($availableTotalLines / $numClients);
        $spareLines = $availableTotalLines - ($minLinesPerClient * $numClients);

        $lines = array_slice(
            explode("\n", $data),
            1, // remove the first {
            // at least 1 line to write for the client -- might push the height limit, is ok
            max(1, $minLinesPerClient +
                // add another "spare" line for the first x clients
                (array_search($clientId, array_keys($this->tableInput)) < $spareLines ? 1 : 0))
        );
        $clientData = collect($lines)
            ->map(function ($str) use ($allowedDataCharsWidth) {
                return substr($str, 4, $allowedDataCharsWidth);
            })
            ->implode("\n");
         $clientInfo = $this->wsServer->getClientInfo($clientId) ?? [];
         $this->tableInput[$clientId] = [
            $clientId . ' t' . ($clientInfo['team_id'] ?? '') . ' u' . ($clientInfo['user'] ?? ''),
            date("H:i:s"),
            substr($eventName, 9), // removes the EVENT_ON_ prefix from event name
            $clientData
         ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WsServer::EVENT_ON_CLIENT_CONNECTED => 'notifyWsServerDataChange',
            WsServer::EVENT_ON_CLIENT_DISCONNNECTED => 'notifyWsServerDataChange',
            WsServer::EVENT_ON_CLIENT_ERROR => 'notifyWsServerDataChange',
            WsServer::EVENT_ON_CLIENT_MESSAGE_RECEIVED => 'notifyWsServerDataChange',
            WsServer::EVENT_ON_CLIENT_MESSAGE_SENT => 'notifyWsServerDataChange',
            WsServer::EVENT_ON_STATS_UPDATE => 'notifyWsServerDataChange'
        ];
    }
}
