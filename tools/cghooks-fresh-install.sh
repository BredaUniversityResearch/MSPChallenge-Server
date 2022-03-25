#!/bin/bash

if [ ! -d ".git/" ]
then
    echo "Please run this script from the git root folder"
    exit 1;
fi

if [ ! -d "vendor/" ]
then
    echo "Please run 'composer install' to create the vendor folder"
    exit 2;
fi

if [ ! -f "vendor/bin/cghooks" ]
then
    echo "Could not find cghooks, is it installed? See https://github.com/BrainMaestro/composer-git-hooks'"
    exit 3;
fi

git merge --quiet HEAD &> /dev/null
result=$?
if [ $result -ne 0 ]
then
    echo "Merge in progress. Cannot install cghooks. Please re-run ${0} after merge."
    exit 4
fi

./vendor/bin/cghooks remove -f post-merge pre-push
./vendor/bin/cghooks add --ignore-lock
exit 0
