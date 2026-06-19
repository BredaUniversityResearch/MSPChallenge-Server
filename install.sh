#!/usr/bin/env bash
# Use strict shell mode: exit on errors, undefined vars, and pipeline failures.
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"

die() {
  echo "$*" >&2
  exit 1
}

to_native_path() {
  local path="$1"
  if command -v cygpath >/dev/null 2>&1; then
    cygpath -w "$path"
  else
    printf '%s\n' "$path"
  fi
}

run_composer() {
  if [[ "${COMPOSER_BINARY}" == *.phar ]]; then
    APP_ENV="${APP_ENV}" "${PHP_BINARY}" "${COMPOSER_BINARY}" "$@"
  else
    APP_ENV="${APP_ENV}" "${COMPOSER_BINARY}" "$@"
  fi
}

source "${SCRIPT_DIR}/tools/resolve-app-env.sh"

bash "${SCRIPT_DIR}/set_symfony_version.sh" "${@:1}"

if [[ -z "${PHP_BINARY:-}" ]]; then
  PHP_BINARY="$(command -v php || true)"
fi
[[ -n "${PHP_BINARY}" ]] || die "Could not find php binary."

if [[ -z "${COMPOSER_BINARY:-}" ]]; then
  if command -v composer >/dev/null 2>&1; then
    COMPOSER_BINARY="$(command -v composer)"
  elif [[ -f "${SCRIPT_DIR}/composer.phar" ]]; then
    COMPOSER_BINARY="${SCRIPT_DIR}/composer.phar"
  else
    die "Could not find Composer. Add composer to PATH or place composer.phar in ${SCRIPT_DIR}."
  fi
fi

# On some Windows/Git Bash setups, `composer` resolves to the installed launcher script
# next to composer.phar (for example /c/ProgramData/ComposerSetup/bin/composer), but that
# wrapper may still fail depending on shell/path translation. If a sibling composer.phar
# exists, prefer it and invoke it explicitly through PHP.
if [[ "${COMPOSER_BINARY}" != *.phar && -f "${COMPOSER_BINARY}.phar" ]]; then
  COMPOSER_BINARY="${COMPOSER_BINARY}.phar"
fi

# Convert Git Bash style paths to Windows paths before passing .phar to php.exe
if [[ "${COMPOSER_BINARY}" == *.phar ]]; then
  COMPOSER_BINARY="$(to_native_path "${COMPOSER_BINARY}")"
fi

COMPOSER_ARGS=()
if [[ "${APP_ENV}" == "prod" ]]; then
  COMPOSER_ARGS+=(--no-dev)
fi

run_composer check-platform-reqs
run_composer install --prefer-dist --no-progress --no-interaction "${COMPOSER_ARGS[@]}"
run_composer dump-autoload

APP_ENV="${APP_ENV}" bash "${SCRIPT_DIR}/tools/install-tools.sh"

OPENSSL_CONF_DEFAULT=""
if [[ -n "${EXEPATH:-}" ]]; then
  OPENSSL_CONF_DEFAULT="${EXEPATH}\mingw64\ssl\openssl.cnf" # Git Bash started from git-bash.exe
  if [[ ! -f "${OPENSSL_CONF_DEFAULT}" ]]; then
    OPENSSL_CONF_DEFAULT="${EXEPATH}\..\mingw64\ssl\openssl.cnf" # Git Bash started from bin/bash.exe / Windows Terminal
  fi
fi

if [[ -n "${OPENSSL_CONF_DEFAULT}" && -f "${OPENSSL_CONF_DEFAULT}" ]]; then
  export OPENSSL_CONF="${OPENSSL_CONF_DEFAULT}"
fi

CONSOLE_PATH="$(to_native_path "${SCRIPT_DIR}/bin/console")"
"${PHP_BINARY}" "${CONSOLE_PATH}" lexik:jwt:generate-keypair --skip-if-exists

# shellcheck disable=SC1091
source "${SCRIPT_DIR}/docker-aliases.sh"

exit 0
