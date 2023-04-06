#!/bin/bash

# aliases
# some windows php handling to prevent error: `output is not a tty`. Use php.exe instead of php.
if [ "${OS}" == "Windows_NT" ]; then
  alias php=php.exe
fi
# ede = export (e) dotenv (d) environmental variables (e)
alias ede='unset $(docker/dotenv-vars.sh) && export $(php docker/export-dotenv-vars/app.php $(docker/dotenv-vars.sh))'
# dcu = docker(d) compose(c) up(u)
DCU_BASE="MSYS_NO_PATHCONV=1 BLACKFIRE_SERVER_ID=$BLACKFIRE_SERVER_ID BLACKFIRE_SERVER_TOKEN=$BLACKFIRE_SERVER_TOKEN BLACKFIRE_CLIENT_ID=$BLACKFIRE_CLIENT_ID BLACKFIRE_CLIENT_TOKEN=$BLACKFIRE_CLIENT_TOKEN CADDY_MERCURE_JWT_SECRET=$CADDY_MERCURE_JWT_SECRET docker compose"
alias dcu="ede && SERVER_NAME=:80 $DCU_BASE up -d --remove-orphans"
# dcu + xdebug (x)
alias dcux="ede && XDEBUG_MODE=debug SERVER_NAME=:80 $DCU_BASE up -d --remove-orphans"
# dcu + production (p)
alias dcup='ede && ([[ "${APP_ENV}" == "prod" ]] || (echo "Could not find APP_ENV=prod in dotenv" && exit 1)) && '"$DCU_BASE -f docker-compose.yml -f docker-compose.prod.yml up -d --remove-orphans"
ALIAS_DL_BASE="docker logs"
[[ -z "${PHP_CONTAINER}" ]] && PHP_CONTAINER='mspchallenge-server-php-1'
# dl = docker(d) logs(l) with default container mspchallenge-server-php-1
alias dl="$ALIAS_DL_BASE $PHP_CONTAINER"
# dl + mspchallenge-server-blackfire-1 (b)
alias dlb="$ALIAS_DL_BASE mspchallenge-server-blackfire-1"
# dl + mspchallenge-server-caddy-1 (c)
alias dlc="$ALIAS_DL_BASE mspchallenge-server-caddy-1"
# dl + mspchallenge-server-database-1 (d)
alias dld="$ALIAS_DL_BASE mspchallenge-server-database-1"
# de = docker(d) execute(e) with container mspchallenge-server-php-1
ALIAS_DE_BASE='MSYS_NO_PATHCONV=1 docker exec'
alias de="$ALIAS_DE_BASE $PHP_CONTAINER"
# de + supervisor (s)
alias des="de /usr/bin/supervisord -c /etc/supervisord.conf"
# de + supervisorctl (sc)
alias desc="echo -e '[status|start|stop|restart] [all|app-ws-server|msw]\n'; de supervisorctl"
# de + list (l) + log (l) + supervisor (s)
alias dells="de ls -l /var/log/supervisor/"
# de + tail log (tl)
alias detl='f() { ([[ "$1" != "" ]] || (echo "Please specify one of these files names:" && dells && exit 1)) && (echo "press Ctrl+C to exit tail log"; de tail -f /var/log/supervisor/$1); unset -f f; } ; f'
# de + tail log (tl) + websocket server (w)
alias detlw="detl app-ws-server.log"
# de + tail log (tl) + msw (m)
alias detlm="detl msw.log"
# de + top (t)
alias det='de top'
# de + choose profile (cpf) to "Choose Blackfire profile on running websocket server process"
alias decpf='de pkill -SIGUSR1 -f "php bin/console app:ws-server"'
# de + choose profile (rpf) to "Run Blackfire profile on running websocket server process"
alias derpf='de pkill -SIGUSR2 -f "php bin/console app:ws-server"'
# de + Run websocket server manually (wss)
ALIAS_WSS='php /srv/app/bin/console app:ws-server'
alias dewss="de $ALIAS_WSS"
# dewss + xdebug (x)
alias dewssx="$ALIAS_DE_BASE -e XDEBUG_SESSION=1 -e PHP_IDE_CONFIG="serverName=symfony" $PHP_CONTAINER $ALIAS_WSS"