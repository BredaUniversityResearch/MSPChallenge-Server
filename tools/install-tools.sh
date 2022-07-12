#!/bin/bash

CWD="$(pwd)"
SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
cd "${SCRIPT_DIR}" || exit 4

DOWNLOAD_DIR="../var/tools/"
DOTENV_FILE="../.env"
DOTENV_LOCAL_FILE="../.env.local"
if [[ ! -f $DOTENV_FILE ]]; then
  echo "Could not find file ${DOTENV_FILE}"
  exit 1
fi

# export all .env variables as environmental variables, e.g. APP_ENV
if [[ -z "${APP_ENV}" ]]; then
  echo "No APP_ENV enviromental variable found, checking .env file"
  set -o allexport; source "${DOTENV_FILE}"; set +o allexport
  if [[ -f $DOTENV_LOCAL_FILE ]]; then
    echo "Checking .env.local file"
    set -o allexport; source "${DOTENV_LOCAL_FILE}"; set +o allexport
  fi
fi

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

case $APP_ENV in
    prod|dev) echo "Environment: ${APP_ENV}" ;;
    *) echo "Encountered invalid environment: ${APP_ENV}" ;;
esac

if [[ $FORCE == 1 ]]; then
  echo 'Forcing download to latest versions...'
fi

function download() {
  # install platform independent tools -- runs on both Linux Ubuntu or Windows Git bash
  if [[ $FORCE == 1 || ! -f "${DOWNLOAD_DIR}phpcs.phar" ]]; then
    curl --create-dirs -Lso "${DOWNLOAD_DIR}phpcs.phar" https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar
  fi
  if [[ $FORCE == 1|| ! -f "${DOWNLOAD_DIR}phpcbf.phar" ]]; then
    curl --create-dirs -Lso "${DOWNLOAD_DIR}phpcbf.phar" https://squizlabs.github.io/PHP_CodeSniffer/phpcbf.phar
  fi

  # install Windows tools
  if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" ]]; then
    # install Symfony cli
    if [[ $FORCE == 1 || ! -f "${DOWNLOAD_DIR}Win/symfony-cli/symfony.exe" ]]; then
      if [[ ! -d "${DOWNLOAD_DIR}Win/symfony-cli/" ]]; then
        mkdir -p "${DOWNLOAD_DIR}Win/symfony-cli/"
      fi
      curl -Lso /tmp/symfony-cli_windows_386.zip https://github.com/symfony-cli/symfony-cli/releases/latest/download/symfony-cli_windows_386.zip && unzip -qqo /tmp/symfony-cli_windows_386.zip -d "${DOWNLOAD_DIR}Win/symfony-cli/" && rm /tmp/symfony-cli_windows_386.zip
    fi
  fi

  downloadDevTools
}

function downloadDevTools() {
  if [[ $APP_ENV != "dev" ]]; then
    echo "Skipping download of development tools"
    return
  fi

  if [[ $FORCE == 1 || ! -f ./../public/adminer/index.php ]]; then
    curl --create-dirs -Lso ./../public/adminer/adminer.php https://www.adminer.org/latest-mysql-en.php
    cat > ./../public/adminer/index.php <<- CONTENT
<?php
use App\Domain\Common\DatabaseDefaults;
use Symfony\Component\Dotenv\Dotenv;

function adminer_object()
{
    class MyAdminer extends Adminer
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
    return new MyAdminer;
}

include "./adminer.php";
CONTENT
  fi
}

function makeExecutable() {
  TARGET_FILE="${DOWNLOAD_DIR}${1}"
  cat > "${TARGET_FILE}" <<- CONTENT
#!/bin/bash
SCRIPT_DIR="\$( cd -- "\$( dirname -- "\${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
php "\${SCRIPT_DIR}/$1.phar" "\$@"
CONTENT
  chmod u+x "${TARGET_FILE}"
}

function makeExecutables() {
  if [[ $FORCE == 1 || ! -f phpcs ]]; then
    makeExecutable phpcs
  fi
  if [[ $FORCE == 1 || ! -f phpcbf ]]; then
    makeExecutable phpcbf
  fi
}

download
makeExecutables
cd "${CWD}" || exit 5
