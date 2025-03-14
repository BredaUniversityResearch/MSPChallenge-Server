<?php

namespace App\Domain\Services;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class SchemeEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $env = $getEnv($name);

        return str_replace('://', '', $env ?? 'http');
    }

    public static function getProvidedTypes(): array
    {
        return [
            'scheme' => 'string',
        ];
    }
}
