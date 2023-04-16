#!/bin/bash

# aliases
# some windows php handling to prevent error: `output is not a tty`. Use php.exe instead of php.
if [ "${OS}" == "Windows_NT" ]; then
  alias php=php.exe
fi
# ede = export (e) dotenv (d) environmental variables (e)
alias ede='unset $(docker/dotenv-vars.sh) && export $(php docker/export-dotenv-vars/app.php $(docker/dotenv-vars.sh))'
# dcu = docker(d) compose(c) up(u)
DCU_BASE="MSYS_NO_PATHCONV=1 BLACKFIRE_SERVER_ID=${BLACKFIRE_SERVER_ID} BLACKFIRE_SERVER_TOKEN=${BLACKFIRE_SERVER_TOKEN} BLACKFIRE_CLIENT_ID=${BLACKFIRE_CLIENT_ID} BLACKFIRE_CLIENT_TOKEN=${BLACKFIRE_CLIENT_TOKEN} CADDY_MERCURE_JWT_SECRET=${CADDY_MERCURE_JWT_SECRET} docker compose"
alias dcu="ede && ${DCU_BASE} up -d --remove-orphans"
# dcu + xdebug (x)
alias dcux="ede && XDEBUG_MODE=debug ${DCU_BASE} up -d --remove-orphans"
# dcu + production (p)}
alias dcup='ede && ([[ "${APP_ENV}" == "prod" ]] || (echo "Could not find APP_ENV=prod in dotenv" && exit 1)) && '"${DCU_BASE} -f docker-compose.yml -f docker-compose.prod.yml up -d --remove-orphans"
ALIAS_DL_BASE="docker logs"
[[ -z "${CONTAINER_PREFIX}" ]] && CONTAINER_PREFIX="mspchallenge-server"
[[ -z "${PHP_CONTAINER}" ]] && PHP_CONTAINER="${CONTAINER_PREFIX}-php-1"
[[ -z "${DATABASE_CONTAINER}" ]] && DATABASE_CONTAINER="${CONTAINER_PREFIX}-database-1"
# dl = docker(d) logs(l) with default container mspchallenge-server-php-1
alias dl="${ALIAS_DL_BASE} ${PHP_CONTAINER}"
# dl + blackfire (b)
alias dlb="${ALIAS_DL_BASE} ${CONTAINER_PREFIX}-blackfire-1"
# dl + caddy (c)
alias dlc="${ALIAS_DL_BASE} ${CONTAINER_PREFIX}-caddy-1"
# dl + database (d)
alias dld="${ALIAS_DL_BASE} ${DATABASE_CONTAINER}"
# de = docker(d) execute(e) with container php
ALIAS_DE_BASE='MSYS_NO_PATHCONV=1 docker exec'
alias de="${ALIAS_DE_BASE} ${PHP_CONTAINER}"
# de + supervisor (s)
alias des="de /usr/bin/supervisord -c /etc/supervisord.conf"
# de + supervisorctl (sc)
alias desc="echo -e '[status|start|stop|restart] [all|app-ws-server|msw]\n'; de supervisorctl"
# de + list (l) + log (l) + supervisor (s)
alias dells="de ls -l /var/log/supervisor/"
# de + tail log (tl)
alias detl='f() { ([[ "$1" != "" ]] || (echo "Please specify one of these files names:" && dells && exit 1)) && (echo "press Ctrl+C to exit tail log"; de tail -f /var/log/supervisor/$1 ; de pkill -f "tail -f /var/log/supervisor/$1"); unset -f f; } ; f'
# de + tail log (tl) + websocket server (w)
alias detlw="detl app-ws-server.log"
# de + tail log (tl) + msw (m)
alias detlm="detl msw.log"
# de + top (t)
alias det='de top; de pkill -f top'
# de + choose profile (cpf) to "Choose Blackfire profile on running websocket server process"
alias decpf='de pkill -SIGUSR1 -f "php bin/console app:ws-server"'
# de + choose profile (rpf) to "Run Blackfire profile on running websocket server process"
alias derpf='de pkill -SIGUSR2 -f "php bin/console app:ws-server"'
# de + Run websocket server manually (wss)
ALIAS_WSS='php /srv/app/bin/console app:ws-server'
alias dewss="de ${ALIAS_WSS}"
# dewss + xdebug (x)
alias dewssx="${ALIAS_DE_BASE} -e XDEBUG_SESSION=1 -e PHP_IDE_CONFIG="serverName=symfony" ${PHP_CONTAINER} ${ALIAS_WSS}"
# docker (d) run (r) grafana (g)
MY2_SETUP="ede && (MSYS_NO_PATHCONV=1 docker exec ${DATABASE_CONTAINER} bash -c 'mysql -u root -p${DATABASE_PASSWORD} < /root/my2_80.sql' || echo 'Failed to import my2_80.sql to database')"
alias drg="${MY2_SETUP} && docker stop grafana ; docker rm grafana ; MSYS_NO_PATHCONV=1 docker run -d -p 3000:3000 -e MY2_PASSWORD=${MY2_PASSWORD} --name=grafana --label com.docker.compose.project=mspchallenge-server --network=mspchallenge-server_database --volume \"$PWD/docker/grafana/provisioning:/etc/grafana/provisioning\" --volume \"$PWD/docker/grafana/msp-challenge/:/etc/grafana/msp-challenge\" grafana/grafana-oss:9.1.7"
# docker (d) run (r) mitmproxy (m)
alias drm="docker stop mitmproxy ; docker rm mitmproxy ; MSYS_NO_PATHCONV=1 docker run -d -p 8080:8080 -p 8081:8081 --name=mitmproxy --label com.docker.compose.project=mspchallenge-server --network=mspchallenge-server_php mitmproxy/mitmproxy:9.0.1 mitmweb --web-host 0.0.0.0 --mode reverse:http://caddy --set websocket=true"
