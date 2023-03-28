#!/bin/sh
if [[ ! -f .env ]]; then
  exit 0
fi

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd)
ARGS=$(eval "${SCRIPT_DIR}/dotenv-vars.sh")
ENVS=$(php -r 'require_once __DIR__."/vendor/autoload_runtime.php"; $dotenv=new \Symfony\Component\Dotenv\Dotenv(); $dotenv->loadEnv(__DIR__."/.env"); unset($argv[0]); foreach ($argv as $env) echo $env."=".$_ENV[$env]." ";' $ARGS)
echo $ENVS
exit 0
