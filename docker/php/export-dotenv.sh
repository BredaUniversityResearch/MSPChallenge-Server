#!/bin/sh
if [[ ! -f .env ]]; then
  exit 0
fi
ARGS='WEB_SERVER_PORT WATCHDOG_PORT WS_SERVER_PORT APP_ENV' # separated by space
ENVS=$(php -r 'require_once __DIR__."/vendor/autoload_runtime.php"; $dotenv=new \Symfony\Component\Dotenv\Dotenv(); $dotenv->loadEnv(__DIR__."/.env"); unset($argv[0]); foreach ($argv as $env) if (($_ENV[$env] ?? null) !== null) echo $env."=".$_ENV[$env]." ";' $ARGS)
echo $ENVS
exit 0
