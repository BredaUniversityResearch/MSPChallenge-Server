<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'cache' => [
            'app' => $_ENV['FRAMEWORK_CACHE_APP'] ?? 'cache.adapter.filesystem',
        ],
    ]);
};
