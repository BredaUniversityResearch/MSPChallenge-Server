# MSPChallenge-Server

## Contribution rules
- Any contribution to the project must be proposed through a Pull Request.
- The branch you are creating the PR from, shall have the same name of the Jira issue you are working on.
- All Pull Requests need to be reviewed by at least one member of the MSP development team before being merged.
- All Pull Requests shall contain only 1 commit possibly. If you have more than consider squashing them.
- Remember to put the Jira issue number in the commit message.
- Set _.gitmessage_ as your commit message template by running the following command from git bash:
```sh
 git config commit.template .gitmessage
```

## Getting Started using Symfony Docker

MSP Challenge's Docker configuration is based on [Symfony Docker](https://github.com/dunglas/symfony-docker).
Make sure you do not run XAMPP Services, to free up port 80/443 (the web server) and 3306 (database server), such that they can be run by Docker

1. If not already done, install either:
   - [Docker Desktop](https://www.docker.com/products/docker-desktop/) (recommended)
   - or: [Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)
2. Run `docker compose build --pull --no-cache` to build fresh images
3. Run `docker compose up` (the logs will be displayed in the current shell) <br />
   Run `docker compose up -d` (so with -d) to detach from the compose process. <br />
   You can view the logs by running `docker logs mspchallenge-server-php-1`, or in Docker Desktop by opening "Containers" -> "php-1" -> "Actions" -> "View Details" <br />
   - For dev, to disable HTTPS:<br/>
     Add environmental variable SERVER_NAME with value :80, e.g. like this: Run `SERVER_NAME=:80 docker compose up -d`
4. Wait for the logs to show "NOTICE: ready to handle connection"
5. Open `http://localhost/ServerManager` in your favorite web browser to open up the Server manager
6. Run `docker compose down --remove-orphans` to stop the Docker containers.

## Blackfire

Fill-in your Blackfire Server id+token and client id+token below and run:<br/>
`BLACKFIRE_SERVER_ID=... BLACKFIRE_SERVER_TOKEN=... BLACKFIRE_CLIENT_ID=... BLACKFIRE_CLIENT_TOKEN=... docker compose up -d`
For dev also prepend with:<br/>
`SERVER_NAME=:80`

Now you can simply profile from CLI using `blackfire run php script.php`

## Supervisor

To start supervisor, run:<br/>
`docker exec mspchallenge-server-php-1 /usr/bin/supervisord -c /etc/supervisord.conf`<br/>
Or, from Git bash:<br/>
`MSYS_NO_PATHCONV=1 docker exec mspchallenge-server-php-1 /usr/bin/supervisord -c /etc/supervisord.conf`<br/>

To start/stop/restart supervisor services, see some examples here:<br/>
`docker exec mspchallenge-server-php-1 supervisorctl start all`<br/>
`docker exec mspchallenge-server-php-1 supervisorctl start app-ws-server`<br/>
`docker exec mspchallenge-server-php-1 supervisorctl start msw`<br/>
`docker exec mspchallenge-server-php-1 supervisorctl stop all`<br/>
`docker exec mspchallenge-server-php-1 supervisorctl restart all`<br/>

To check their status:<br/>
`docker exec mspchallenge-server-php-1 supervisorctl status all`<br/>

## Aliases for development

If the host machine running Docker is Linux, or your have a Linux-based terminal like WSL or Git bash on Windows, you can add aliases in the .bashrc file in your home directory using below lines.
Note that we use SERVER_NAME=:80 in alias dcu to disable https.

Once you have created the .bashrc file you need to log-out and -in, reboot the system, or restart the terminal. Then, just type "alias" to see a list of them.

```
# export settings
# you can leave the Blackfire ids and tokens empty if you do not intend to use it
export BLACKFIRE_CLIENT_ID=
export BLACKFIRE_CLIENT_TOKEN=
export BLACKFIRE_SERVER_ID=
export BLACKFIRE_SERVER_TOKEN=

# aliases
# dcu = docker(d) compose(c) up(u)
alias dcu="SERVER_NAME=:80 BLACKFIRE_SERVER_ID=$BLACKFIRE_SERVER_ID BLACKFIRE_SERVER_TOKEN=$BLACKFIRE_SERVER_TOKEN BLACKFIRE_CLIENT_ID=$BLACKFIRE_CLIENT_ID BLACKFIRE_CLIENT_TOKEN=$BLACKFIRE_CLIENT_TOKEN docker compose up -d --remove-orphans && des"
# dcu + xdebug (x)
alias dcux="XDEBUG_MODE=debug dcu"
ALIAS_DL_BASE="docker logs"
PHP_CONATINER='mspchallenge-server-php-1'
# dl = docker(d) logs(l) with default container mspchallenge-server-php-1
alias dl="$ALIAS_DL_BASE $PHP_CONATINER"
# dl + mspchallenge-server-blackfire-1 (b)
alias dlb="$ALIAS_DL_BASE mspchallenge-server-blackfire-1"
# dl + mspchallenge-server-caddy-1 (c)
alias dlc="$ALIAS_DL_BASE mspchallenge-server-caddy-1"
# dl + mspchallenge-server-database-1 (d)
alias dld="$ALIAS_DL_BASE mspchallenge-server-database-1"
# de = docker(d) execute(e) with container mspchallenge-server-php-1
ALIAS_DE_BASE='MSYS_NO_PATHCONV=1 docker exec'
alias de="$ALIAS_DE_BASE $PHP_CONATINER"
# de + supervisor (s)
alias des="de /usr/bin/supervisord -c /etc/supervisord.conf"
# de + supervisorctl (sc)
alias desc="echo -e '[status|start|stop|restart] [all|app-ws-server|msw]\n'; de supervisorctl"
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
alias dewssx="$ALIAS_DE_BASE -e XDEBUG_SESSION=1 -e PHP_IDE_CONFIG="serverName=symfony" $PHP_CONATINER $ALIAS_WSS"
```

## Symfony Docker features

* Production, development and CI ready
* [Installation of extra Docker Compose services](docs/extra-services.md) with Symfony Flex
* Automatic HTTPS (in dev and in prod!)
* HTTP/2, HTTP/3 and [Preload](https://symfony.com/doc/current/web_link.html) support
* Built-in [Mercure](https://symfony.com/doc/current/mercure.html) hub
* [Vulcain](https://vulcain.rocks) support
* Native [XDebug](docs/xdebug.md) integration
* Just 2 services (PHP FPM and Caddy server)
* Super-readable configuration

## More docs on Symfony Docker

1. [Build options](docs/build.md)
2. [Support for extra services](docs/extra-services.md)
3. [Deploying in production](docs/production.md), and also how to disable https
4. [Debugging with Xdebug](docs/xdebug.md)
5. [TLS Certificates](docs/tls.md)
6. [Using a Makefile](docs/makefile.md)
7. [Troubleshooting](docs/troubleshooting.md)

## Sponsors and credits

Thanks to all of them for their great support:

- [Blackfire.io](https://blackfire.io/), the profiling and monitoring service for PHP
- [Symfony Docker](https://github.com/dunglas/symfony-docker), created by [Kévin Dunglas](https://dunglas.fr), co-maintained by [Maxime Helias](https://twitter.com/maxhelias) and sponsored by [Les-Tilleuls.coop](https://les-tilleuls.coop).

## License

[Symfony Docker](https://github.com/dunglas/symfony-docker) is available under the MIT License.