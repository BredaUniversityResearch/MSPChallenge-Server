<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();

        // Not during a build
        if (isset($_ENV['IS_BUILD'])) {
            return;
        }

        // Only in production
        if ($_ENV['APP_ENV'] !== 'prod') {
            return;
        }

        $invalidEnvVars = [];
        $dotenv = new Dotenv();
        $defaults = $dotenv->parse(file_get_contents(dirname(__DIR__).'/.env.dev'));
        foreach (array_keys($defaults) as $var) {
            $current = $_ENV[$var] ?? '';
            $default = $defaults[$var] ?? '';
            if ($current === $default || empty($current)) {
                $invalidEnvVars[] = $var;
            }
        }
        if (!empty($invalidEnvVars)) {
            throw new \RuntimeException(
                sprintf(
                    'Found insecure environment variables: %s, they cannot be equal to the default value!',
                    implode(', ', $invalidEnvVars)
                )
            );
        }
    }
}
