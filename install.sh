#!/bin/bash

setGitHooksPostsCheckout() {
    cat > .git/hooks/post-checkout <<- 'CONTENT'
#!/bin/bash
PREV_COMMIT=$1
POST_COMMIT=$2
NOCOLOR='\e[0m]';
REDCOLOR='\e[37;41m';
if [[ -f composer.lock ]]; then
  DIFF=`git diff --shortstat $PREV_COMMIT..$POST_COMMIT composer.lock`
  if [[ $DIFF != "" ]]; then
    echo -e "$REDCCOLOR composer.lock has changed. You must run "bash install.sh"$NOCOLOR"
  fi
fi
CONTENT
}

setSymfonyVersion() {
  VERSION_DEFAULT="5.4"
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

setGitHooksPostsCheckout
setSymfonyVersion "${@:1}"
set -e

if [[ -z $COMPOSER_BINARY ]]; then
   COMPOSER_BINARY=$(which composer)
fi

"$COMPOSER_BINARY" install
"$COMPOSER_BINARY" dump-autoload -o $COMPOSER_ARGS

exit 0
