<?php

use App\Domain\Services\ConnectionManager;
use Symfony\Config\Doctrine\DbalConfig;
use Symfony\Config\Doctrine\OrmConfig;
use Symfony\Config\DoctrineConfig;
use Symfony\Config\StofDoctrineExtensionsConfig;

// a good yaml/php example of the doctrine configuration, see:
//   https://symfony.com/doc/current/doctrine/multiple_entity_managers.html
return static function (DoctrineConfig $doctrineConfig, StofDoctrineExtensionsConfig $stofDoctrineExtensionsConfig) {
    $connectionManager = ConnectionManager::getInstance();
    $serverManagerDbName = $connectionManager->getServerManagerDbName();
    $dbNames = [];
    for ($gameSessionId = 1; $gameSessionId < ($_ENV['DATABASE_MAX_GAME_SESSION_DBS'] ?? 9999); $gameSessionId++) {
        $dbNames[] = $connectionManager->getGameSessionDbName($gameSessionId);
    }
    $dbalConfig = new DbalConfig();
    $dbalConfig->connection('default', $connectionManager->getConnectionConfig($_ENV['DBNAME_SESSION_PREFIX'].'1'));
    $dbalConfig->connection($serverManagerDbName, $connectionManager->getConnectionConfig($serverManagerDbName));
    $dbalConfig->defaultConnection('default');
    $ormConfig = new OrmConfig([
        # Since doctrine/doctrine-bundle 2.11:
        #   Not setting "doctrine.orm.enable_lazy_ghost_objects" to true is deprecated.
        # @todo(MH): for now, still set to false.
        #   Otherwise the GameConfigVersion::getGameConfigCompleteRaw() will return null.
        'enable_lazy_ghost_objects' => false
    ]);

    $ormConfig
        ->defaultEntityManager('default')
        # https://stackoverflow.com/questions/79131843/how-to-set-auto-generate-proxy-classes-to-autogenerate-eval-when-using-doctrine
        ->autoGenerateProxyClasses(Doctrine\ORM\Proxy\ProxyFactory::AUTOGENERATE_EVAL);
    $ormConfig->entityManager('default', $connectionManager->getEntityManagerConfig('default'));
    $ormConfig->entityManager(
        $serverManagerDbName,
        $connectionManager->getServerEntityManagerConfig($serverManagerDbName)
    );
    foreach ($dbNames as $dbName) {
        $dbalConfig->connection($dbName, $connectionManager->getConnectionConfig($dbName));
        $ormConfig->entityManager($dbName, $connectionManager->getEntityManagerConfig($dbName));
    }
    $doctrineConfig->dbal($dbalConfig);
    $doctrineConfig->orm($ormConfig);

    $mapping = [
        'timestampable' => true,
        'softdeleteable' => true
    ];
    $stofDoctrineExtensionsConfig->defaultLocale('en_us');
    $stofDoctrineExtensionsConfig->orm('default', $mapping);
    foreach ($dbNames as $dbName) {
        $stofDoctrineExtensionsConfig->orm($dbName, $mapping);
    }

    if (($_ENV['APP_ENV'] ?? null) !== 'prod') {
        return;
    }
    $ormConfig
        ->autoGenerateProxyClasses(false)
        ->proxyDir('%kernel.build_dir%/doctrine/orm/Proxies');
};
