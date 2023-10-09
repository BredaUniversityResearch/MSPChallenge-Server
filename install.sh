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

if [[ "${DOCKER}" == "1" ]]; then
  echo "Docker detected, so JWT keypair generation will run normally."
  eval "php bin/console lexik:jwt:generate-keypair --skip-if-exists"
else
  echo "Not in Docker, so assuming Windows, altering JWT keypair generation command."
  OPENSSL_CONF_DEFAULT="${EXEPATH}\..\mingw64\ssl\openssl.cnf"
  if [ -z "${OPENSSL_CONF}" ] && [ -n "${EXEPATH}" ] && [ -f "${OPENSSL_CONF_DEFAULT}" ]; then
      OPENSSL_CONF="${OPENSSL_CONF_DEFAULT}"
  fi
  eval "OPENSSL_CONF=\"${OPENSSL_CONF}\" php bin/console lexik:jwt:generate-keypair --skip-if-exists"
fi
if [ $? -ne 0 ]; then
  echo "Could not install JWT encoding key pair."
  exit 1
fi

source docker-aliases.sh
exit 0
