# MSPChallenge-Server

## Contribution rules
- Any contribution to the project must be proposed through a Pull Request.
- The branch you are creating the PR from, shall have the same name of the Jira issue you are working on.
- All Pull Requests need to be reviewed by at least one member of the MSP development team before being merged.
- All Pull Requests shall contain only 1 commit possibly. If you have more then consider squashing them.
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
   You can view the logs in Docker Desktop by opening "Containers" -> "php-1" -> "Actions" -> "View Details" <br />
   - For dev, to disable HTTPS:<br/>
     Add environmental variable SERVER_NAME with value :80, e.g. like this: Run `SERVER_NAME=:80 docker compose up -d`
4. Wait for the logs to show "NOTICE: ready to handle connection"
5. Open `http://localhost/ServerManager` in your favorite web browser to open up the Server manager
6. Run `docker compose down --remove-orphans` to stop the Docker containers.

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