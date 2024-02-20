<?php
namespace App\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Symfony\Component\Filesystem\Filesystem;

class GameSessionLogger extends GameSessionLoggerBase
{
    protected function write(LogRecord $record): void
    {
        $this->handleWriteByArray($record->toArray());
    }
}
