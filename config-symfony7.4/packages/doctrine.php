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

    // From Symfony 8 / DoctrineBundle 3+ uses native lazy objects; legacy proxy options are intentionally omitted.
    $ormConfig = [
        # Native lazy objects (PHP 8.4+) replace the legacy proxy-based lazy ghost objects
        # for uninitialized entity *references*. This is unrelated to, and does not
        # affect, the app's own GameConfigVersion::getGameConfig*Raw() lazy-loading, which
        # is populated via a #[ORM\PostLoad] listener (App\Entity\Trait\LazyLoadersTrait),
        # not via Doctrine's entity-proxy mechanism.
        # Note: with this enabled, doctrine-bundle no longer generates/uses proxy classes at
        # all, so "auto_generate_proxy_classes"/"proxy_dir" are intentionally omitted here;
        # DoctrineExtension explicitly skips setAutoGenerateProxyClasses()/setProxyDir() in
        # this case (see vendor/doctrine/doctrine-bundle/src/DependencyInjection/DoctrineExtension.php).
        'enable_native_lazy_objects' => true,
        'default_entity_manager' => 'default',
        'entity_managers' => $ormEntityManagers,
        # Explicitly disable the (deprecated) controller argument auto-mapping feature;
        # this codebase does not rely on it (no entity-typed controller action arguments).
        'controller_resolver' => [
            'auto_mapping' => false,
        ],
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
