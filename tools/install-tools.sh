#!/bin/bash

CWD="$(pwd)"
SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
cd "${SCRIPT_DIR}"

function download() {
  FORCE=0
  if [[ "$1" == "force" ]]; then
    FORCE=1
  fi

  # install platform independent tools -- runs on both Linux unbuntu or Windows Git bash
  if [[ $FORCE == 1 || ! -f phpcs.phar ]]; then
    curl -OLs https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar
  fi
  if [[ $FORCE == 1 || ! -f phpmd.phar ]]; then
    curl -OLs https://phpmd.org/static/latest/phpmd.phar
  fi
  if [[ $FORCE == 1 || ! -f phpcbf.phar ]]; then
    curl -OLs https://squizlabs.github.io/PHP_CodeSniffer/phpcbf.phar
  fi

  # install Windows tools
  if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" ]]; then
    # install Symfony cli
    if [[ $FORCE == 1 || ! -f ./Win/symfony-cli/symfony.exe ]]; then
      curl -Lso /tmp/symfony-cli_windows_386.zip https://github.com/symfony-cli/symfony-cli/releases/latest/download/symfony-cli_windows_386.zip && unzip -qqo /tmp/symfony-cli_windows_386.zip -d ./Win/symfony-cli/ && rm /tmp/symfony-cli_windows_386.zip
    fi
  fi
}

function makeExecutable() {
  cat > "$1" <<- CONTENT
#!/bin/bash
SCRIPT_DIR="\$( cd -- "\$( dirname -- "\${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
php "\${SCRIPT_DIR}/$1.phar" "\$@"
CONTENT
}

function makeExecutables() {
  FORCE=0
  if [[ "$1" == "force" ]]; then
    FORCE=1
  fi

  if [[ $FORCE == 1 || ! -f phpcs ]]; then
    makeExecutable phpcs
  fi
  if [[ $FORCE == 1 || ! -f phpcbf ]]; then
    makeExecutable phpcbf
  fi
  if [[ $FORCE == 1 || ! -f phpmd ]]; then
    makeExecutable phpmd
  fi
}

download
makeExecutables
cd "${CWD}"
