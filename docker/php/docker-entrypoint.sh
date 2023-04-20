#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then

  echo Running $1...

	bash install.sh

	if grep -q ^DATABASE_URL= .env; then
		# After the installation, the following block can be deleted
		if [ "$CREATION" = "1" ]; then
			echo "To finish the installation please press Ctrl+C to stop Docker Compose and run: docker compose up --build"
			sleep infinity
		fi

		echo "Waiting for db to be ready..."
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for db to be ready... Or maybe the db is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left"
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo "The database is not up or not reachable:"
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo "The db is now ready and reachable"
		fi

		if [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
			php bin/console doctrine:migrations:migrate --no-interaction
		fi
	fi

	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX export
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX export
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX raster
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX raster
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX running_session_config
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX running_session_config
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX ServerManager/log
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX ServerManager/log
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX ServerManager/saves
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX ServerManager/saves
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX ServerManager/session_archive
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX ServerManager/session_archive
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX session_archive
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX session_archive
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var

  echo "Starting supervisor..."
  rm -f /run/supervisord.sock ; /usr/bin/supervisord -c /etc/supervisord.conf
fi

exec docker-php-entrypoint "$@"
