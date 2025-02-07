#!/bin/bash

# generates a random 32 char long string
CADDY_MERCURE_JWT_SECRET=$(echo $RANDOM | md5sum | head -c 32)

if [[ "${DOCKER}" == "1" ]]; then
  echo "Docker detected, skipping aliases"
  exit 0
fi

# aliases
# some windows php handling to prevent error: `output is not a tty`. Use php.exe instead of php.
if [ "${OS}" == "Windows_NT" ]; then
  alias php=php.exe
fi
[[ -z "${COMPOSE_PROJECT_NAME}" ]] && COMPOSE_PROJECT_NAME="mspchallenge"
# ede = export (e) dotenv (d) environmental variables (e)
alias ede='unset $(bash docker/dotenv-vars.sh) && export $(php docker/export-dotenv-vars/app.php $(bash docker/dotenv-vars.sh))'
# dcu = docker(d) compose(c) up(u)
PRE_DCU="bash set_symfony_version.sh && mkdir -p ./var/docker/ && touch ./var/docker/.bash_history && rm -rf ./node_modules"
DCU_BASE="MSYS_NO_PATHCONV=1 docker compose"
alias dcu="ede && $PRE_DCU && COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME}-dev" ${DCU_BASE} -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.adminer.yml up -d --remove-orphans"
# dcu + xdebug (x)
alias dcux="ede && $PRE_DCU && COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME}-dev" XDEBUG_MODE=debug ${DCU_BASE} -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.adminer.yml up -d --remove-orphans"
# dcu + production (p)}
alias dcup='ede && ([[ "${APP_ENV}" == "prod" ]] || (echo "Could not find APP_ENV=prod in dotenv" && exit 1)) && '"$PRE_DCU && ${DCU_BASE} -f docker-compose.yml -f docker-compose.prod.yml up -d --remove-orphans"
# dcu + hybrid (h)}
alias dcus='ede && '"$PRE_DCU && APP_ENV=prod COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME}-staging" ${DCU_BASE} -f docker-compose.yml -f docker-compose.staging.yml -f docker-compose.adminer.yml up -d --remove-orphans"
ALIAS_DL_BASE="docker logs"
# dl = docker(d) logs(l) with default container mspserver-php
alias dl="${ALIAS_DL_BASE} mspserver-php"
# dl + blackfire (b)
alias dlb="${ALIAS_DL_BASE} mspserver-blackfire"
# dl + database (d)
alias dld="${ALIAS_DL_BASE} mspserver-mariadb"
# dl + simulations (s)
alias dls="${ALIAS_DL_BASE} mspserver-simulations"
# de = docker(d) execute(e) with container mspserver-php
ALIAS_DE_BASE='MSYS_NO_PATHCONV=1 docker exec'
alias de="${ALIAS_DE_BASE} mspserver-php"
# de + supervisor (s)
alias des="de /usr/bin/supervisord -c /etc/supervisord.conf"
# de + supervisorctl (sc)
alias desc="echo -e '[status|start|stop|restart] [all|app-ws-server|msw|messenger-consume|messenger-watchdog:*]\n'; de supervisorctl"
# de + list (l) + log (l) + supervisor (s)
alias dells="de ls -l /var/log/supervisor/"
detl_finish() {
  de sh -c "pgrep -af \"tail -f /var/log/supervisor/\" | grep -E '[0-9]+\\stail' | awk '{print \$1}' | xargs kill > /dev/null 2>&1"
}
# de + tail log (tl)
alias detl='f() { ([[ "$1" != "" ]] || (echo "Please specify one of these files names:" && dells && exit 1)) && (echo "press Ctrl+C to exit tail log"; de sh -c "tail -f /var/log/supervisor/$1" ; detl_finish); unset -f f; } ; f'
# shellcheck disable=SC2142
# de + tail log (tl) + websocket server (w)
alias detlw="detl app-ws-server.log"
# de + tail log (tl) + messenger-consume (mc)
alias detlmc="detl messenger-consume.log"
# de + tail log (tl) + messenger watchdog (mw)
alias detlmw='detl messenger-watchdog-*.log'
# de + top (t)
alias det='de top; de pkill -f top'
# de + phpstan (p)
alias dep='de php vendor/bin/phpstan analyse'
# de + choose profile (cpf) to "Choose Blackfire profile on running websocket server process"
alias decpf='de pkill -SIGUSR1 -f "php bin/console app:ws-server"'
# de + choose profile (rpf) to "Run Blackfire profile on running websocket server process"
alias derpf='de pkill -SIGUSR2 -f "php bin/console app:ws-server"'
# de + Run websocket server manually (wss)
ALIAS_WSS='php /app/bin/console app:ws-server'
alias dewss="de ${ALIAS_WSS}"
# dewss + xdebug (x)
alias dewssx="${ALIAS_DE_BASE} -e XDEBUG_SESSION=1 -e PHP_IDE_CONFIG="serverName=symfony" mspserver-php ${ALIAS_WSS}"
# docker (d) run (r) grafana (g)
if [ -z "${DATABASE_PASSWORD}" ]; then
    MYSQL_PARAMS=""
else
    MYSQL_PARAMS="-p${DATABASE_PASSWORD}"
fi
MY2_SETUP="ede && (MSYS_NO_PATHCONV=1 docker exec mspserver-mariadb bash -c 'mysql -u root ${MYSQL_PARAMS} < /root/my2_80.sql' || echo 'Failed to import my2_80.sql to database')"
alias drg="${MY2_SETUP} && docker stop grafana ; docker rm grafana ; MSYS_NO_PATHCONV=1 docker run -d -p 3000:3000 -e MY2_PASSWORD=${MY2_PASSWORD} --name=grafana --label com.docker.compose.project=mspchallenge --network=mspchallenge_database --volume \"$PWD/docker/grafana/provisioning:/etc/grafana/provisioning\" --volume \"$PWD/docker/grafana/msp-challenge/:/etc/grafana/msp-challenge\" grafana/grafana-oss:9.1.7"
# docker (d) + stop (s) + all containers (a)
alias dsa='docker stop $(docker ps -a -q)'
# docker (d) + system (s) + prune (p)
alias dsp='docker system prune -a -f'
# docker (d) + clean
alias dclean='dsa ; dsp'
