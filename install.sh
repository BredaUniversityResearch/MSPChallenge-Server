#!/bin/bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
source "${SCRIPT_DIR}/tools/resolve-app-env.sh"

bash set_symfony_version.sh "${@:1}"
set -e

if [[ -z $COMPOSER_BINARY ]]; then
   COMPOSER_BINARY=$(which composer)
fi

COMPOSER_ARGS=""
if [[ "${APP_ENV}" == "prod" ]]; then
  COMPOSER_ARGS="--no-dev"
fi

eval "APP_ENV=${APP_ENV} ${COMPOSER_BINARY} check-platform-reqs && APP_ENV=${APP_ENV} ${COMPOSER_BINARY} install --prefer-dist --no-progress --no-interaction ${COMPOSER_ARGS} && APP_ENV=${APP_ENV} ${COMPOSER_BINARY} dump-autoload -o ${COMPOSER_ARGS}"
if [ $? -ne 0 ]; then
  echo "Composer install & dump-autoload failed."
  exit 1
fi
eval "APP_ENV=${APP_ENV} bash tools/install-tools.sh"
if [ $? -ne 0 ]; then
  echo "Could not install tools."
  exit 1
fi
eval "php bin/console lexik:jwt:generate-keypair --skip-if-exists"
if [ $? -ne 0 ]; then
  echo "Could not install JWT encoding key pair."
  exit 1
fi
source docker-aliases.sh
exit 0
