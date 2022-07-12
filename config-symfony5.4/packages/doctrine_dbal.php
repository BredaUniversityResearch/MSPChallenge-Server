<?php

use App\Domain\Services\ConnectionManager;;
use Symfony\Config\DoctrineConfig;

return static function (DoctrineConfig $doctrineConfig) {
    $connectionManager = ConnectionManager::getInstance();
    $dbNames = [
        $connectionManager->getServerManagerDbName()
    ];
    for ($gameSessionId = 1; $gameSessionId < ($_ENV['DATABASE_MAX_GAME_SESSION_DBS'] ?? 9999); $gameSessionId++) {
        $dbNames[] = $connectionManager->getGameSessionDbName($gameSessionId);
    }
    $connectionConfigs = [];
    foreach ($dbNames as $dbName) {
        $connectionConfigs[$dbName] = $connectionManager->getConnectionConfig($dbName);
    }
    $doctrineConfig->dbal([
        'default_connection' => $connectionManager->getServerManagerDbName(),
        'connections' => $connectionConfigs
    ]);
};
