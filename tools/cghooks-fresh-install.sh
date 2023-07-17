#!/bin/bash

if [[ "${DOCKER}" == "1" ]]; then
  echo "Docker detected, canceling cghooks installation"
  exit 0
fi

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
DOTENV_FILE="../.env"
DOTENV_LOCAL_FILE="../.env.local"
source "${SCRIPT_DIR}/resolve-app-env.sh"

if [[ "${APP_ENV}" == "prod" ]]; then
  echo "Skipping install cghooks"
  exit 0
fi

if [ ! -d ".git/" ]
then
    echo "Please run this script from the git root folder"
    exit 1
fi

if [ ! -d "vendor/" ]
then
    echo "Please run 'composer install' to create the vendor folder"
    exit 2
fi

if [ ! -f "vendor/bin/cghooks" ]
then
    echo "Could not find cghooks, is it installed? See https://github.com/BrainMaestro/composer-git-hooks'"
    exit 3
fi

git merge --quiet HEAD &> /dev/null
result=$?
if [ $result -ne 0 ]
then
    echo -e "Merge in progress. \e[31mCannot install cghooks. Please re-run ${0} manually after merge.\e[0m"
    exit 4
fi

# remove hooks here that we did use, but do not want to use anymore, e.g.
# ./vendor/bin/cghooks remove -f post-merge pre-push

# this will add hooks that need updating
./vendor/bin/cghooks add --ignore-lock --force

result=$?
if [ $result -ne 0 ]
then
    echo -e "\e[31mFailed to add cghooks\e[0m"
    exit 5
fi

echo -e "\e[32mSuccessfully added cghooks\e[0m"
exit 0
