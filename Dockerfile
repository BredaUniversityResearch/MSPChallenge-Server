#syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.3 AS frankenphp_upstream

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target

# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

VOLUME /app/var/

# persistent / runtime deps
# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
		acl \
		file \
		gettext \
		git \
        libgdiplus \
        bash \
        supervisor \
        default-mysql-client \
        procps \
        nano \
        && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
        @composer \
		apcu \
		intl \
		opcache \
		zip \
        pcntl \
        imagick \
        gd \
	;

# if you want to debug on prod, enable below lines:
##   Also check ./frankenphp/conf.d/app.prod.ini
#ENV XDEBUG_MODE=debug
#RUN set -eux; \
#	install-php-extensions \
#		xdebug \
#	;

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

###> recipes ###
###> doctrine/doctrine-bundle ###
RUN docker-php-ext-install mysqli pdo pdo_mysql
###< doctrine/doctrine-bundle ###
###< recipes ###

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/caddy/Caddyfile

# write command history to a history file
RUN echo 'export HISTFILE=/root/.bash_history' >> /root/.bashrc
# to force the command history to be written out, even if the shell is not exited properly
RUN echo "export PROMPT_COMMAND='history -a'" >> /root/.bashrc

# Supervisor
RUN mkdir -p /var/log/supervisor/
COPY --link docker/supervisor/supervisord.conf /etc/supervisord.conf
RUN mkdir -p /etc/supervisor.d/
COPY --link docker/supervisor/supervisor.d/*.ini /etc/supervisor.d/

ENTRYPOINT ["docker-entrypoint"]

# Install Blackfire Probe
RUN version=$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION.(PHP_ZTS ? '-zts' : '');") \
    && architecture=$(uname -m) \
    && curl -A "Docker" -o /tmp/blackfire-probe.tar.gz -D - -L -s https://blackfire.io/api/v1/releases/probe/php/linux/$architecture/$version \
    && mkdir -p /tmp/blackfire \
    && tar zxpf /tmp/blackfire-probe.tar.gz -C /tmp/blackfire \
    && mv /tmp/blackfire/blackfire-*.so $(php -r "echo ini_get ('extension_dir');")/blackfire.so \
    && printf "extension=blackfire.so\nblackfire.agent_socket=tcp://blackfire:8307\n" > $PHP_INI_DIR/conf.d/blackfire.ini \
    && rm -rf /tmp/blackfire /tmp/blackfire-probe.tar.gz

# Install Blackfire CLI
RUN mkdir -p /tmp/blackfire \
    && architecture=$(uname -m) \
    && curl -A "Docker" -L https://blackfire.io/api/v1/releases/cli/linux/$architecture | tar zxp -C /tmp/blackfire \
    && mv /tmp/blackfire/blackfire /usr/bin/blackfire \
    && rm -Rf /tmp/blackfire

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]

# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
	install-php-extensions \
    	xdebug \
    ;

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch" ]

# Prod FrankenPHP image
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod
# this line enables the Blazing-fast performance thanks to the worker mode of FrankenPHP
#   @todo however, disable for MSP, gives request issues in ServerManager
# ENV FRANKENPHP_CONFIG="import worker.Caddyfile"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/
COPY --link frankenphp/worker.Caddyfile /etc/caddy/worker.Caddyfile

# Replace symbolic links with COPY --link
COPY --link config-symfony6.4 config
COPY --link composer-symfony6.4.json composer.json
COPY --link composer-symfony6.4.lock composer.lock
COPY --link symfony6.4.lock symfony.lock
COPY --link package.json package.json
# copy sources
COPY --link . ./

# Install dependencies
RUN set -eux; \
  composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# Install PHP dependencies
RUN set -eux; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; sync;

# Clean up unnecessary files
RUN rm -rf frankenphp/

FROM node:22.6 as node_base

RUN mkdir -p /home/node/app/node_modules /home/node/app/public/build \
    && chown -R node:node /home/node/app/node_modules \
    && chown -R node:node /home/node/app/public/build

FROM mariadb:10.11.10 AS mariadb_base
FROM blackfire/blackfire:2 AS blackfire_base
FROM shyim/adminerevo AS adminer_base
FROM mitmproxy/mitmproxy:10.3.1 as mitmproxy_base
FROM redis:7.2.4-alpine AS redis_base
FROM erikdubbelboer/phpredisadmin as phpredisadmin_base
