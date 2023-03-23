#!/bin/sh
if [[ ! -f .env ]]; then
  exit 0
fi
ARGS='WATCHDOG_PORT' # separated by space
ENVS=$(php -r 'require_once __DIR__."/vendor/autoload_runtime.php"; $dotenv=new \Symfony\Component\Dotenv\Dotenv(); $dotenv->loadEnv(__DIR__."/.env"); unset($argv[0]); foreach ($argv as $env) if (($_ENV[$env] ?? null) !== null) echo $env."=".$_ENV[$env]." ";' $ARGS)
echo $ENVS
exit 0
