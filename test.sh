#!/bin/bash
set -e

function summary() {
  ./tools/phpcs --standard=PSR2 --report=summary -n "$1"
}

function lint() {
  ./tools/phpcs --standard=PSR2 -n "$1"
}

function detectMess() {
  ./tools/phpmd "$1" text cleancode,codesize,controversial
}

function fix() {
  ./tools/phpcbf --standard=PSR2 "$1"
}

PATHS="
src
ServerManager/config.php
legacy.php
"

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