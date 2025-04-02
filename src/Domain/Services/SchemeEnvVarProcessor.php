<?php

namespace App\Domain\Services;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class SchemeEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): ?string
    {
        $env = $getEnv($name);

        if ($env === null) {
            return null;
        }

        return str_replace('://', '', $env);
    }

    public static function getProvidedTypes(): array
    {
        return [
            'scheme' => 'string',
        ];
    }
}
