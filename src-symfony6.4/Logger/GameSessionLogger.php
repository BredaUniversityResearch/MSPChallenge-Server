<?php
namespace App\Logger;

use Monolog\LogRecord;

class GameSessionLogger extends GameSessionLoggerBase
{
    protected function write(LogRecord $record): void
    {
        $arr = $record->toArray();
        $arr['formatted'] = $record->formatted;
        $this->handleWriteByArray($arr);
    }
}
