<?php

namespace App\Domain\WsServer\Console;

use App\Domain\Event\NameAwareEvent;
use App\Domain\Helper\Util;
use App\Domain\WsServer\ClientHeaderKeys;
use App\Domain\WsServer\WsServerEventDispatcherInterface;
use Symfony\Component\Console\Terminal;

class ClientsView extends TableViewBase
{
    const WIDTH_RESERVED_CHARS_CLIENT = 17;
    const WIDTH_RESERVED_CHARS_TIME = 8;
    const WIDTH_RESERVED_CHARS_EVENT_NAME = 23;
    const WIDTH_RESERVED_CHARS_TABLE = 15;
    // excl. data column.
    const WIDTH_RESERVED_CHARS =
        self::WIDTH_RESERVED_CHARS_CLIENT +
        self::WIDTH_RESERVED_CHARS_TIME +
        self::WIDTH_RESERVED_CHARS_EVENT_NAME +
        self::WIDTH_RESERVED_CHARS_TABLE;

    protected array $tableInput = [];

    public function getName(): string
    {
        return 'clients';
    }

    protected function process(NameAwareEvent $event): void
    {
        $terminal = new Terminal();
        $allowedDataCharsWidth = max(0, $terminal->getWidth() - self::WIDTH_RESERVED_CHARS);

        if (null !== $clientIds = $event->getSubject()) {
            $clientDataContainer = $event->getArguments();
            // convert to array
            if (!is_array($clientIds)) {
                $clientDataContainer = [$clientIds => $clientDataContainer];
                $clientIds = [$clientIds];
            }
            foreach ($clientIds as $clientId) {
                if ($event->getEventName() == WsServerEventDispatcherInterface::EVENT_ON_CLIENT_DISCONNECTED) {
                    unset($this->tableInput[$clientId]);
                    continue;
                }
                $clientData = $clientDataContainer[$clientId];
                $this->processTableInput($clientId, $event->getEventName(), $clientData, $allowedDataCharsWidth);
            }
        }

        $numClients = count($this->tableInput);
        $this->table
            ->setStyle('box')
            ->setHeaders(['client', 'time', 'event', 'data'])
            ->setRows($this->tableInput)
            ->setColumnWidth(0, self::WIDTH_RESERVED_CHARS_CLIENT)
            ->setColumnWidth(1, self::WIDTH_RESERVED_CHARS_TIME)
            ->setColumnWidth(2, self::WIDTH_RESERVED_CHARS_EVENT_NAME)
            ->setColumnWidth(3, $allowedDataCharsWidth)
            ->setHeaderTitle('Clients')
            ->setFooterTitle($numClients . ' connected / started: ' . $this->startDateTime . ' / mem: ' .
                Util::getHumanReadableSize(memory_get_usage()))
        ;
    }

    private function truncateValuesRecursive(array|object $array, int $maxValueLength): array
    {
        if (is_object($array)) {
            $array = get_object_vars($array);
        }
        return collect($array)
            ->map(function ($value) use ($maxValueLength) {
                if (null === $value) {
                    return null;
                }
                if (is_array($value)) {
                    return $this->truncateValuesRecursive($value, $maxValueLength);
                }
                if (is_object($value)) {
                    return $this->truncateValuesRecursive($value, $maxValueLength);
                }
                return substr($value, 0, $maxValueLength);
            })
            ->all();
    }

    private function processEventAndDataColumns(
        int $clientId,
        string $eventName,
        array $clientData,
        int $allowedDataCharsWidth
    ): array {
        $numClients = count($this->tableInput);
        if ($eventName == WsServerEventDispatcherInterface::EVENT_ON_CLIENT_CONNECTED) {
            $numClients += 1;
        }
        $numClients = max(1, $numClients);
        $data = json_encode($this->truncateValuesRecursive($clientData, 16));

        $terminal = new Terminal();
        $availableTotalLines = $this->terminalHeight ?? $terminal->getHeight();
        $availableTotalLines -= self::HEIGHT_RESERVED_LINES;
        $availableTotalLines = max(1, $availableTotalLines); // at least 1 line left to write
        $minLinesPerClient = floor($availableTotalLines / $numClients);
        $spareLines = $availableTotalLines - ($minLinesPerClient * $numClients);

        static $linesCache = [];
        $linesCache[$clientId][0] ??= []; // event column
        $linesCache[$clientId][1] ??= []; // data column
        $eventColumnInput = &$linesCache[$clientId][0];
        $dataColumnInput = &$linesCache[$clientId][1];

        $eventName = substr($eventName, 9); // removes the EVENT_ON_ prefix from event name
        $eventColumnInput = array_merge([$eventName], $eventColumnInput);
        $eventColumnInput = array_slice(
            $eventColumnInput,
            0,
            // at least 1 line to write for the client -- might push the height limit, is ok
            max(1, $minLinesPerClient +
                // add another "spare" line for the first x clients
                (array_search($clientId, array_keys($this->tableInput)) < $spareLines ? 1 : 0))
        );
        $eventColumn = collect($eventColumnInput)->implode("\n");

        $dataColumnInput = array_merge([$data], $dataColumnInput);
        $dataColumnInput = array_slice(
            $dataColumnInput,
            0,
            // at least 1 line to write for the client -- might push the height limit, is ok
            max(1, $minLinesPerClient +
                // add another "spare" line for the first x clients
                (array_search($clientId, array_keys($this->tableInput)) < $spareLines ? 1 : 0))
        );
        $dataColumn = collect($dataColumnInput)
            ->map(function ($str) use ($allowedDataCharsWidth) {
                return substr($str, 0, $allowedDataCharsWidth);
            })
            ->implode("\n");
        return [$eventColumn,$dataColumn];
    }

    private function processTableInput(
        int $clientId,
        string $eventName,
        array $clientData,
        int $allowedDataCharsWidth
    ): void {
        $clientInfo = $this->clientConnectionResourceManager->getClientInfo($clientId) ?? [];
        $clientHeaders = $this->clientConnectionResourceManager->getClientHeaders($clientId) ?? [];
        list($eventColumn,$dataColumn) = $this->processEventAndDataColumns(
            $clientId,
            $eventName,
            $clientData,
            $allowedDataCharsWidth
        );
        $this->tableInput[$clientId] = [
            sprintf(
                '%1$04d g%2$02d t%3$02d u%4$03d',
                $clientId,
                $clientHeaders[ClientHeaderKeys::HEADER_KEY_GAME_SESSION_ID] ?? '',
                $clientInfo['team_id'] ?? '',
                $clientInfo['user'] ?? ''
            ),
            date("H:i:s"),
            $eventColumn ?? $this->tableInput[$clientId][2] ?? '',
            $dataColumn ?? $this->tableInput[$clientId][3] ?? ''
        ];
    }
}
