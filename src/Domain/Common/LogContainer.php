<?php

namespace App\Domain\Common;

class LogContainer
{
    const LOG_FIELD_TIME = 'time';
    const LOG_FIELD_MICROTIME = 'microtime';
    const LOG_FIELD_LEVEL = 'level';
    const LOG_FIELD_MESSAGE = 'message';

    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_ERROR = 'error';

    private array $logs = [];

    public function log(string $message, string $level = self::LOG_LEVEL_INFO): void
    {
        $this->logs[] = [
            self::LOG_FIELD_TIME => time(),
            self::LOG_FIELD_MICROTIME => microtime(true),
            self::LOG_FIELD_LEVEL => $level,
            self::LOG_FIELD_MESSAGE => $message
        ];
    }

    public function hasLogs(?string $levelFilter = null): bool
    {
        if ($levelFilter !== null) {
            return !empty(array_filter($this->logs, fn($log) => $log[self::LOG_FIELD_LEVEL] === $levelFilter));
        }
        return !empty($this->logs);
    }

    public function getLogMessages(?string $levelFilter = null): array
    {
        return array_map(fn($log) => $log[self::LOG_FIELD_MESSAGE], $this->getLogs($levelFilter));
    }

    public function getLogs(?string $levelFilter = null): array
    {
        if ($levelFilter !== null) {
            return array_filter($this->logs, fn($log) => $log[self::LOG_FIELD_LEVEL] === $levelFilter);
        }
        return $this->logs;
    }

    public function appendFromLogContainer(LogContainer $logContainer): void
    {
        $this->logs = array_merge($this->logs, $logContainer->getLogs());
    }

    public function clearLogs(): void
    {
        $this->logs = [];
    }
}
