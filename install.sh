#!/bin/bash

# to enable git-for-windows symlinks, see here: https://github.com/git-for-windows/git/pull/156
export MSYS=winsymlinks:nativestrict
VERSION_DEFAULT="5.4"

setSymfonyVersion() {
  VERSION=$1
  if [[ -z $1 ]]; then
    VERSION="$VERSION_DEFAULT"
  fi
  COMPOSER_ARGS="${@:2}"

  COMPOSER_JSON_FILE="composer-symfony${VERSION}.json"
  COMPOSER_LOCK_FILE="composer-symfony${VERSION}.lock"
  SYMFONY_LOCK_FILE="symfony${VERSION}.lock"
  CONFIG_DIR="config-symfony${VERSION}"

  if [[ ! -d $CONFIG_DIR ]]; then
    echo "Folder ${CONFIG_DIR} does not exist."
    exit 1
  fi

  if [[ ! -f $COMPOSER_JSON_FILE ]]; then
    echo "File ${COMPOSER_JSON_FILE} does not exist."
    exit 1
  fi

  echo "Installing Symfony version ${VERSION}"

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
    ln -s "$COMPOSER_LOCK_FILE" composer.lock
  fi

  if [[ -f $SYMFONY_LOCK_FILE ]]; then
    rm -f symfony.lock
    ln -s "$SYMFONY_LOCK_FILE" symfony.lock
  fi
}

setSymfonyVersion "${@:1}"
set -e

if [[ -z $COMPOSER_BINARY ]]; then
   COMPOSER_BINARY=$(which composer)
fi

"$COMPOSER_BINARY" install
"$COMPOSER_BINARY" dump-autoload -o $COMPOSER_ARGS

exit 0
