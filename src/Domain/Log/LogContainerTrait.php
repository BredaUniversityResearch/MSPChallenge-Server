<?php

namespace App\Domain\Log;

trait LogContainerTrait
{
    private array $logs = [];

    public function log(string $message, string $level = LogContainerInterface::LOG_LEVEL_INFO): void
    {
        // only allow extensive debug logging in dev environment
        if ($level == LogContainerInterface::LOG_LEVEL_DEBUG && ($_ENV['APP_ENV'] ?? 'prod') !== 'dev') {
            return;
        }
        $this->logs[] = [
            LogContainerInterface::LOG_FIELD_TIME => time(),
            LogContainerInterface::LOG_FIELD_MICROTIME => microtime(true),
            LogContainerInterface::LOG_FIELD_LEVEL => $level,
            LogContainerInterface::LOG_FIELD_MESSAGE => $message
        ];
    }

    public function hasLogs(?string $levelFilter = null): bool
    {
        if ($levelFilter !== null) {
            return !empty(
                array_filter($this->logs, fn($log) => $log[LogContainerInterface::LOG_FIELD_LEVEL] === $levelFilter)
            );
        }
        return !empty($this->logs);
    }

    public function getLogMessages(?string $levelFilter = null): array
    {
        return array_map(fn($log) => $log[LogContainerInterface::LOG_FIELD_MESSAGE], $this->getLogs($levelFilter));
    }

    public function getLogs(?string $levelFilter = null): array
    {
        if ($levelFilter !== null) {
            return array_filter($this->logs, fn($log) => $log[LogContainerInterface::LOG_FIELD_LEVEL] === $levelFilter);
        }
        return $this->logs;
    }

    public function appendFromLogContainer(LogContainerInterface $logContainer): void
    {
        $this->logs = array_merge($this->logs, $logContainer->getLogs());
    }

    public function clearLogs(): void
    {
        $this->logs = [];
    }
}
