#!/bin/bash
set -e

function summary() {
  ./var/tools/phpcs --standard=PSR2 --report=summary "$1"
}

function lint() {
  ./var/tools/phpcs --standard=PSR2 "$1" -s
}

function fix() {
  ./var/tools/phpcbf --standard=PSR2 "$1"
}

PATHS="
src/
api/
api_test/
legacy.php
ServerManager/api/
ServerManager/install/install.php
ServerManager/bootstrap.php
ServerManager/config.php
ServerManager/index.php
ServerManager/init.php
ServerManager/login.php
ServerManager/logout.php
ServerManager/manager.php
"

bash ./tools/install-tools.sh
for p in $PATHS
do
  if [[ "$1" == "--fix" ]]; then
    echo "fixing $p"
    fix "$p"
  elif [[ "$1" == "--verbose" ]]; then
    lint "$p"
  else
    summary "$p"
  fi
done
