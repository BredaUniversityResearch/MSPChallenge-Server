#!/bin/bash

CWD="$(pwd)"
SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
cd "${SCRIPT_DIR}" || exit 4

DOWNLOAD_DIR="../var/tools/"

FORCE=0
while getopts ":fe:" opt; do
  case $opt in
    f) FORCE=1
    ;;
    e) APP_ENV="$OPTARG"
    ;;
    \?) echo "Invalid option -$OPTARG" >&2
    exit 2
    ;;
  esac

  case $OPTARG in
    -*) echo "Option $opt needs a valid argument"
    exit 3
    ;;
  esac
done

DOTENV_FILE="../.env"
DOTENV_LOCAL_FILE="../.env.local"
source resolve-app-env.sh

if [[ $FORCE == 1 ]]; then
  echo 'Forcing download to latest versions...'
fi

function download() {
  # install Windows tools
  if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" ]]; then
    # install Symfony cli
    if [[ $FORCE == 1 || ! -f "${DOWNLOAD_DIR}Win/symfony-cli/symfony.exe" ]]; then
      if [[ ! -d "${DOWNLOAD_DIR}Win/symfony-cli/" ]]; then
        mkdir -p "${DOWNLOAD_DIR}Win/symfony-cli/"
      fi
      curl -Lso /tmp/symfony-cli_windows_386.zip https://github.com/symfony-cli/symfony-cli/releases/latest/download/symfony-cli_windows_386.zip && unzip -qqo /tmp/symfony-cli_windows_386.zip -d "${DOWNLOAD_DIR}Win/symfony-cli/" && rm /tmp/symfony-cli_windows_386.zip
      rm -f ${DOWNLOAD_DIR}/symfony ; ln -s Win/symfony-cli/symfony.exe ${DOWNLOAD_DIR}/symfony
    fi
  # install Linux tools
  elif [[ "$OSTYPE" == "linux-musl" ]]; then
    # install Symfony cli
    if [[ $FORCE == 1 || ! -f "${DOWNLOAD_DIR}Linux/symfony-cli/symfony" ]]; then
      if [[ ! -d "${DOWNLOAD_DIR}Linux/symfony-cli/" ]]; then
        mkdir -p "${DOWNLOAD_DIR}Linux/symfony-cli/"
      fi
      curl -Lso /tmp/symfony-cli_linux_amd64.tar.gz https://github.com/symfony-cli/symfony-cli/releases/latest/download/symfony-cli_linux_amd64.tar.gz && tar -zxf /tmp/symfony-cli_linux_amd64.tar.gz -C "${DOWNLOAD_DIR}Linux/symfony-cli/" && rm /tmp/symfony-cli_linux_amd64.tar.gz
      rm -f ${DOWNLOAD_DIR}/symfony ; ln -s Linux/symfony-cli/symfony ${DOWNLOAD_DIR}/symfony
    fi
  fi

  downloadDevTools
}

function downloadDevTools() {
  if [[ $APP_ENV != "dev" ]]; then
    echo "Skipping download of development tools"
    return
  fi

  # install apc.php
  if [[ $FORCE == 1 || ! -f "${DOWNLOAD_DIR}apc.php" ]]; then
    curl -Lso "${DOWNLOAD_DIR}apc.php" https://raw.githubusercontent.com/krakjoe/apcu/master/apc.php
  fi

  # we do not need adminer on docker, it has its own container.
  if [[ "${DOCKER}" == "1" ]]; then
    echo "Docker detected, skipping adminer installation"
    exit 0
  fi

  if [[ $FORCE == 1 || ! -f ./../public/adminer/index.php ]]; then
    curl --create-dirs -Lso ./../public/adminer/adminer.php https://www.adminer.org/latest-mysql-en.php
    curl --create-dirs -Lso ./../public/adminer/plugins/plugin.php https://raw.github.com/vrana/adminer/master/plugins/plugin.php
    curl --create-dirs -Lso ./../public/adminer/plugins/sql-log.php https://raw.github.com/vrana/adminer/master/plugins/sql-log.php
    cat > ./../public/adminer/index.php <<- CONTENT
<?php
use App\Domain\Common\DatabaseDefaults;
use Symfony\Component\Dotenv\Dotenv;

function adminer_object()
{
    include_once "./plugins/plugin.php";
    foreach (glob("plugins/*.php") as \$filename) {
        include_once "./\$filename";
    }
    \$plugins = [
        new AdminerSqlLog(),
    ];

    class MyAdminer extends AdminerPlugin
    {
        public function login(\$login, \$password)
        {
            return true;
        }
        public function credentials()
        {
            \$baseDir = __DIR__ . '/../../';
            require_once \$baseDir.'vendor/autoload.php';
            \$dotenv = new Dotenv();
            call_user_func_array([\$dotenv, 'load'], glob(\$baseDir . '.env*') ?: []);
            // server, username and password for connecting to database
            return array(
                (
                    \$_ENV['DATABASE_HOST'] ?? DatabaseDefaults::DEFAULT_DATABASE_HOST) . ':' .
                    (\$_ENV['DATABASE_PORT'] ?? DatabaseDefaults::DEFAULT_DATABASE_PORT
                ),
                (\$_ENV['DATABASE_USER'] ?? DatabaseDefaults::DEFAULT_DATABASE_USER),
                ''
            );
        }
    }
    return new MyAdminer(\$plugins);
}

include "./adminer.php";
CONTENT
  fi
}

download
cd "${CWD}" || exit 5
