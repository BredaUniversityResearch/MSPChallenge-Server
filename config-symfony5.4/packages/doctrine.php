<?php

use App\Domain\Services\ConnectionManager;
use Symfony\Config\Doctrine\DbalConfig;
use Symfony\Config\Doctrine\OrmConfig;
use Symfony\Config\DoctrineConfig;

// a good yaml/php example of the doctrine configuration, see:
//   https://symfony.com/doc/current/doctrine/multiple_entity_managers.html
return static function (DoctrineConfig $doctrineConfig) {
    $connectionManager = ConnectionManager::getInstance();
    $dbNames = [
        $connectionManager->getServerManagerDbName()
    ];
    for ($gameSessionId = 1; $gameSessionId < ($_ENV['DATABASE_MAX_GAME_SESSION_DBS'] ?? 9999); $gameSessionId++) {
        $dbNames[] = $connectionManager->getGameSessionDbName($gameSessionId);
    }
    $dbalConfig = new DbalConfig();
    $dbalConfig->defaultConnection($connectionManager->getServerManagerDbName());
    $ormConfig = new OrmConfig([
        'enable_lazy_ghost_objects' => false
    ]);
    $ormConfig
        ->defaultEntityManager($connectionManager->getServerManagerDbName());
    foreach ($dbNames as $dbName) {
        $dbalConfig->connection($dbName, $connectionManager->getConnectionConfig($dbName));
        $ormConfig->entityManager($dbName, $connectionManager->getEntityManagerConfig($dbName));
    }
    $doctrineConfig->dbal($dbalConfig);
    $doctrineConfig->orm($ormConfig);
    if (($_ENV['APP_ENV'] ?? null) !== 'prod') {
        return;
    }
    $ormConfig
        ->autoGenerateProxyClasses(false)
        ->proxyDir('%kernel.build_dir%/doctrine/orm/Proxies');
};
