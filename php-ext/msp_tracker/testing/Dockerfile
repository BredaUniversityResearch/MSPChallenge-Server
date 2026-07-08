FROM dunglas/frankenphp:1-php8.4

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

# Build the local extension from this repository checkout.
COPY php-ext/msp_tracker /usr/src/php/ext/msp_tracker
RUN set -eux; \
    docker-php-ext-install msp_tracker

COPY php-ext/msp_tracker/testing/run-tests.php /app/run-tests.php
COPY php-ext/msp_tracker/testing/docker-entrypoint.sh /usr/local/bin/msp-tracker-test-entrypoint
RUN chmod +x /usr/local/bin/msp-tracker-test-entrypoint

ENTRYPOINT ["msp-tracker-test-entrypoint"]

