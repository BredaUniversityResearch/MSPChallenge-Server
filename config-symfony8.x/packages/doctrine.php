<?php

use App\Domain\Services\ConnectionManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// a good yaml/php example of the doctrine configuration, see:
//   https://symfony.com/doc/current/doctrine/multiple_entity_managers.html
return static function (ContainerConfigurator $container): void {
    $connectionManager = ConnectionManager::getInstance();
    $serverManagerDbName = $connectionManager->getServerManagerDbName();
    $dbNames = [];
    for ($gameSessionId = 1; $gameSessionId < ($_ENV['DATABASE_MAX_GAME_SESSION_DBS'] ?? 9999); $gameSessionId++) {
        $dbNames[] = $connectionManager->getGameSessionDbName($gameSessionId);
    }
    $dbalConnections = [
        'default' => $connectionManager->getConnectionConfig($_ENV['DBNAME_SESSION_PREFIX'].'1'),
        $serverManagerDbName => $connectionManager->getConnectionConfig($serverManagerDbName),
    ];

    $ormEntityManagers = [
        'default' => $connectionManager->getEntityManagerConfig('default'),
        $serverManagerDbName => $connectionManager->getServerEntityManagerConfig($serverManagerDbName),
    ];

    foreach ($dbNames as $dbName) {
        $dbalConnections[$dbName] = $connectionManager->getConnectionConfig($dbName);
        $ormEntityManagers[$dbName] = $connectionManager->getEntityManagerConfig($dbName);
    }


    // Symfony 8 / DoctrineBundle 3+ uses native lazy objects; legacy proxy options are intentionally omitted.
    // Symfony 8 / DoctrineBundle 3+ uses native lazy objects; legacy proxy options are intentionally omitted.
    $ormConfig = [
        'default_entity_manager' => 'default',
        'entity_managers' => $ormEntityManagers,
        'enable_native_lazy_objects' => true,
    ];

    $container->extension('doctrine', [
        'dbal' => [
            'default_connection' => 'default',
            'connections' => $dbalConnections,
        ],
        'orm' => $ormConfig,
    ]);

    $mapping = [
        'timestampable' => true,
        'softdeleteable' => true
    ];
    $ormMappings = ['default' => $mapping];
    foreach ($dbNames as $dbName) {
        $ormMappings[$dbName] = $mapping;
    }

    $container->extension('stof_doctrine_extensions', [
        'default_locale' => 'en_us',
        'orm' => $ormMappings,
    ]);
};

//custom_mapping:
//                    type: annotation
//                    prefix: Client\IntranetBundle\LDAP\
//                    dir: "%kernel.root_dir%/src/Client/IntranetBundle/LDAP/"
//                    is_bundle: false
