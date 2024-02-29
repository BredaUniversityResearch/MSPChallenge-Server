<?php
namespace App\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Symfony\Component\Filesystem\Filesystem;

class GameSessionLogger extends GameSessionLoggerBase
{
    protected function write(array $record): void
    {
        $this->handleWriteByArray($record);
    }
}
