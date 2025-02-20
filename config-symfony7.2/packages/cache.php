<?php

use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $frameworkConfig): void {
    $frameworkConfig->cache()
        ->app($_ENV['FRAMEWORK_CACHE_APP'] ?? 'cache.adapter.filesystem');
};
