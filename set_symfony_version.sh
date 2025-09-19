#!/bin/bash

# to enable git-for-windows symlinks, see here: https://github.com/git-for-windows/git/pull/156
export MSYS=winsymlinks:nativestrict
SYMFONY_VERSION_DEFAULT="6.4"

setSymfonyVersion() {
  [[ ! -z "$1" ]] && SYMFONY_VERSION=$1
  if [[ -z $SYMFONY_VERSION || "${SYMFONY_VERSION}" == "default" ]]; then
    SYMFONY_VERSION="${SYMFONY_VERSION_DEFAULT}"
  fi
  COMPOSER_ARGS="${@:2}"

  COMPOSER_JSON_FILE="composer-symfony${SYMFONY_VERSION}.json"
  COMPOSER_LOCK_FILE="composer-symfony${SYMFONY_VERSION}.lock"
  SYMFONY_LOCK_FILE="symfony${SYMFONY_VERSION}.lock"
  CONFIG_DIR="config-symfony${SYMFONY_VERSION}"

  if [[ ! -d $CONFIG_DIR ]]; then
    echo "Folder ${CONFIG_DIR} does not exist."
    exit 1
  fi

  if [[ ! -f $COMPOSER_JSON_FILE ]]; then
    echo "File ${COMPOSER_JSON_FILE} does not exist."
    exit 1
  fi

  echo "Installing Symfony version ${SYMFONY_VERSION}"

  # if it's not a symlink yet, it is a folder that should become one, so remove it
  if [[ ! -L config ]]; then
    rm -Rf config
  fi

  rm -f config
  ln -s "${CONFIG_DIR}" config
  RETVAL=$?
  if [ $RETVAL -eq 1 ]; then
        printf "Could not create symlink.\n\nHint:\nDid you check 'Enable symbolic links' when installing git-for-windows?\nAnd make sure to run Git Bash as Administator"
	exit 1;
  fi

  rm -f composer.json
  ln -s "${COMPOSER_JSON_FILE}" composer.json

  if [[ -f $COMPOSER_LOCK_FILE ]]; then
    rm -f composer.lock
    ln -s "${COMPOSER_LOCK_FILE}" composer.lock
  fi

  if [[ -f $SYMFONY_LOCK_FILE ]]; then
    rm -f symfony.lock
    ln -s "${SYMFONY_LOCK_FILE}" symfony.lock
  fi
}

setSymfonyVersion "${@:1}"
exit 0
