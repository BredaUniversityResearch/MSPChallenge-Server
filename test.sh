#!/bin/bash
set -e

if [[ ! -f "vendor/bin/phpcs" ]]; then
  echo Could not find vendor/bin/phpcs. Please run 'bash install.sh'
  exit 1;
fi
if [[ ! -f "vendor/bin/phpcbf" ]]; then
  echo Could not find vendor/bin/phpcbf. Please run 'bash install.sh'
  exit 1;
fi

function summary() {
  ./vendor/bin/phpcs --report=summary -d memory_limit=-1
}

function lint() {
  ./vendor/bin/phpcs -s
}

function fix() {
  ./vendor/bin/phpcbf
}

if [[ "$1" == "--fix" ]]; then
  fix
elif [[ "$1" == "--verbose" ]]; then
  lint
else
  summary
fi
