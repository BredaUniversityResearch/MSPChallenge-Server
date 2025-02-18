<?php

namespace App\Domain\Log;

interface LogContainerInterface
{
    const LOG_FIELD_TIME = 'time';
    const LOG_FIELD_MICROTIME = 'microtime';
    const LOG_FIELD_LEVEL = 'level';
    const LOG_FIELD_MESSAGE = 'message';

    const LOG_LEVEL_DEBUG = 'debug';
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_ERROR = 'error';

    public function log(string $message, string $level = self::LOG_LEVEL_INFO): void;
    public function hasLogs(?string $levelFilter = null): bool;
    public function getLogMessages(?string $levelFilter = null): array;
    public function getLogs(?string $levelFilter = null): array;
    public function appendFromLogContainer(self $logContainer): void;
    public function clearLogs(): void;
}
