#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
#	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
#		composer install --prefer-dist --no-progress --no-interaction
#	fi
  bash install.sh

  # Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi

		if [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing # -vvv for very verbose mode
		fi
	fi

  chown -R "$(whoami)":www-data export raster running_session_config ServerManager/configfiles ServerManager/log ServerManager/saves ServerManager/session_archive session_archive var vendor POV
  chmod -R u+rwX,g+rwX,o+rX export raster running_session_config ServerManager/configfiles ServerManager/log ServerManager/saves ServerManager/session_archive session_archive var vendor POV

  echo 'PHP app ready!'

  echo "Starting supervisor..."
  rm -f /run/supervisord.sock ; /usr/bin/supervisord -c /etc/supervisord.conf
fi

exec docker-php-entrypoint "$@"
