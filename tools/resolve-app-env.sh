#!/bin/bash

# export all .env variables as environmental variables, e.g. APP_ENV
if [[ -z "${APP_ENV}" ]]; then
  if [[ -z "${DOTENV_FILE}" ]]; then
    DOTENV_FILE=".env"
  fi

  if [[ -z "${DOTENV_LOCAL_FILE}" ]]; then
    DOTENV_LOCAL_FILE=".env.local"
  fi

  if [[ ! -f $DOTENV_FILE ]]; then
    echo "Could not find file ${DOTENV_FILE}"
    exit 1
  fi

  echo "No APP_ENV enviromental variable found, checking .env file"
  set -o allexport; source "${DOTENV_FILE}"; set +o allexport
  if [[ -f $DOTENV_LOCAL_FILE ]]; then
    echo "Checking .env.local file"
    set -o allexport; source "${DOTENV_LOCAL_FILE}"; set +o allexport
  fi
fi

case $APP_ENV in
    prod|dev) echo "Environment: ${APP_ENV}" ;;
    *) echo "Encountered invalid environment: ${APP_ENV}" ;;
esac
