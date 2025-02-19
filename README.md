# MSPChallenge-Server 

## Getting Started using Symfony Docker

MSP Challenge's Docker configuration is based on [Symfony Docker](https://github.com/dunglas/symfony-docker).

Before starting docker compose, make sure to close any services running on one of the ports listed below.
Otherwise, the container services will not be able to start.

Default ports used by the containers per environment:

| Container       | Service       | Port        | Prod | Staging | Dev | remarks                             | 
|-----------------|---------------|-------------|------|---------|-----|-------------------------------------|
| php-1           | caddy http    | 80          | *    | *       | *   | web server / api                    |
|                 | caddy https   | 443         | *    | *       | *   |
|                 | watchdog      | 45000       | *    | *       | *   | to run simulations                  |
|                 | websocket     | 45001       | *    | *       | *   | direct client-server communication  |
| database-1      | mariadb       | 3306        | *    | *       | *   | to store data                       |
| blackfire-1     | blackfire     | random port | *    | *       | *   | to profile, disabled by default     |
|                 |               |             |      |         |     | ! only enabled given req. env. vars |
| redis-1         | redis         | not exposed | *    | *       | *   | to cache                            |
| adminer-1       | adminer       | 8082        |      | *       | *   | web interface for databases         |
| phpredisadmin-1 | redis admin   | random port |      |         | *   | web interface for redis             |
| mitmproxy-1     | proxy         | 8080        |      |         | *   | to monitor network traffic          |
|                 | web interface | 8081        |      |         | *   | web interface for mitmproxy         |

If you want to change the ports used by the containers, you can do by defining environmental variables. Either:
- define them in your environment, e.g. in your `.bashrc` or `.bash_profile` file
- in the [`.env`](.env) file in the root of the project
- or by defining them in the command line when running `docker compose up` like so, e.g.:
  `WEB_SERVER_PORT=80 DATABASE_PORT=3306 WS_SERVER_PORT=45001 WATCHDOG_PORT=45000 ADMINER_PORT=8082 MITMPROXY_POR=8080 MITMPROXY_WEB_PORT=8081 docker compose up`
- using a `.env.local` file and starting docker compose using aliases, see [Aliases for development and deployment](#aliases-for-development-and-deployment).

## Installation

1. Either clone the repository or download the zip file from the [release page](https://github.com/BredaUniversityResearch/MSPChallenge-Server/releases).
   If you want to contribute to the project, you also can fork the repository. Read more about contributing [here](https://community.mspchallenge.info/wiki/Community_Contribution).
2. If not already done, install either:
   - [Docker Desktop](https://www.docker.com/products/docker-desktop/) (recommended)
   - or: [Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)
3. Run `docker compose build --pull --no-cache` to build fresh images

4. Then, to create the server docker containers, and starting it up, run:
   - for prod: please read this [document](https://community.mspchallenge.info/wiki/Docker_server_installation)
   - for staging: `APP_ENV=prod  docker compose -f docker-compose.yml -f docker-compose.staging.yml -f docker-compose.adminer.yml up -d`
   - for dev: `docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.adminer.yml up -d`

   The `-d` flag is optional and stands for detached mode, meaning the containers will run in the background.   
   Tip: By adding `--remove-orphans` you make sure that no old containers are left running.

   In the table above you can see the differences between the environments.

5. You can view the logs by running `docker logs mspchallenge-php-1`, or in Docker Desktop by opening "Containers" -> "php-1" -> "Actions" -> "View Details"
6. Wait for the logs to show "INFO    FrankenPHP started 🐘    {"php_version": "x.x.x"}"
7. Open `http://localhost/ServerManager` in your favorite web browser to open up the Server manager
8. Run `docker compose down --remove-orphans` to stop the Docker containers.

## Blackfire

Fill-in your Blackfire Server id+token and client id+token below and run:<br/>
`BLACKFIRE_APM_ENABLED=1 BLACKFIRE_SERVER_ID=... BLACKFIRE_SERVER_TOKEN=... BLACKFIRE_CLIENT_ID=... BLACKFIRE_CLIENT_TOKEN=... docker compose up -d --remove-orphans`

Now you can simply profile from CLI using `blackfire run php script.php`

## Supervisor

In below commands, replace CONTAINER with the full name of the php container, e.g.`mspchallenge-dev-php-1`,
`mspchallenge-staging-php-1` or `mspchallenge-php-1` (prod). 
You can list all container names (last column) by running `docker ps`.

To start/stop/restart supervisor services, see some examples here:<br/>
`docker exec CONTAINER supervisorctl start all`<br/>
`docker exec CONTAINER supervisorctl start app-ws-server`<br/>
`docker exec CONTAINER supervisorctl start msw`<br/>
`docker exec CONTAINER supervisorctl stop all`<br/>
`docker exec CONTAINER supervisorctl restart all`<br/>

To check their status:<br/>
`docker exec CONTAINER supervisorctl status all`<br/>

## Aliases for development and deployment

If the host machine, running Docker, is Linux, or your have a Linux-based terminal like WSL or Git bash on Windows, you
can create aliases to simplify docker container management and interaction.

It requires an installation of PHP on your host machine. This is because it uses a
[PHP script](docker/export-dotenv-vars/app.php) to expose the env. vars in the `.env.local` file towards Docker compose.
How to install PHP:
- on Windows, I advise to use [PowerShell PHP manager](https://github.com/mlocati/powershell-phpmanager);
- on Linux, you can install it using your package manager, e.g. `sudo apt-get install php-fpm`.

Then simply run: `source docker-aliases.sh` in your terminal to create the aliases.
Or if you want them to be available all the time, you can add this command in the `.bashrc` file in your home directory.
Once you have created the `.bashrc` file, you need to log-out and -in, reboot the system, or restart the terminal once.

You can type `alias` to see a list of all defined aliases. Or check the [docker-aliases.sh](docker-aliases.sh) file.
The most important aliases being:

| Alias   | Description                                                      |
|---------|------------------------------------------------------------------|
| dcu     | docker(d) compose(c) up(u). This is the dev environment          |
| dcux    | docker(d) compose(c) up(u) xdebug(x). Xdebug enabled             |
| dcus    | docker(d) compose(c) up(u) staging(s)                            |
| dcup    | docker(d) compose(c) up(u) production(p)                         |
| dl      | docker(d) logs(l). Show logs of the php container                |
| dlb     | docker(d) logs(l) blackfire(b). Logs of the blackfire container  |
| dld     | docker(d) logs(l) database(d). Logs of the database container    |
| desc    | docker(d) exec(e) supervisorctl (sc). Manage supervisor services |
| detlw   | docker(d) exec(e) tail log (tl) websocket server (w)             |
| detlm   | docker(d) exec(e) tail log (tl) msw server (m) = watchdog        |
| det     | docker(d) exec(e) top (t). show processes running                |
| dep     | docker(d) exec(e) phpstan (p). Run static analysis with phpstan  |
| drg     | docker (d) run (r) grafana (g). Create a grafana container       |
| dsa     | docker (d) stop (s) all containers (a)                           |
| dsp     | docker (d) system (s) prune (p)                                  |
| dclean  | dsa + dsp. Stop all containers and prune the system              | 

## Sponsors and credits

Thanks to all of them for their great support:

- [Blackfire.io](https://blackfire.io/), the profiling and monitoring service for PHP
- [Symfony Docker](https://github.com/dunglas/symfony-docker), created by [Kévin Dunglas](https://dunglas.fr), co-maintained by [Maxime Helias](https://twitter.com/maxhelias) and sponsored by [Les-Tilleuls.coop](https://les-tilleuls.coop).

## License

[Symfony Docker](https://github.com/dunglas/symfony-docker) is available under the MIT License.

The MSP Challenge Simulation Platform, all its Editions (including but not limited to the North Sea Edition,
Baltic Sea Edition, Clyde Marine Region Edition) and their source codes are provided under the GNU General Public
License Version 3 (GPL-3.0-only) license: https://www.gnu.org/licenses/gpl-3.0.en.html.
Read more about MSP Challenge's Terms & Conditions [here](https://community.mspchallenge.info/wiki/Terms_%26_Conditions).