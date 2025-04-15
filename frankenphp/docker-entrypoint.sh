#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
  if [ -d "config" ]; then
    setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX config
    setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX config
  fi
  if [ -f "composer.json" ]; then
    setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX composer.json
    setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX composer.json
  fi
  if [ -f "composer.lock" ]; then
    setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX composer.lock
    setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX composer.lock
  fi
  if [ -f "symfony.lock" ]; then
    setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX symfony.lock
    setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX symfony.lock
  fi

#	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
#		composer install --prefer-dist --no-progress --no-interaction
#	fi
  bash install.sh

  # Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX config
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX config
  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX composer.json
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX composer.json
  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX composer.lock
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX composer.lock
  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX symfony.lock
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX symfony.lock

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" --connection msp_server_manager 2>&1); do
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

  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX export
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX export
  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX raster
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX raster
  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX running_session_config
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX running_session_config
  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX ServerManager/configfiles
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX ServerManager/configfiles
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
  setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX POV
  setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX POV

  echo 'PHP app ready!'

  echo "Starting supervisor..."
  rm -f /run/supervisord.sock ; /usr/bin/supervisord -c /etc/supervisord.conf
fi

exec docker-php-entrypoint "$@"
