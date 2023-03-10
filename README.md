# MSPChallenge-Server

## Getting Started using Docker Compose

Make sure you do not run XAMPP Services, to free up port 80 (the web server) and 3306 (database server), to be run by Docker

1. If not already done, install either:
   - [Docker Desktop](https://www.docker.com/products/docker-desktop/) (recommended)
   - or: [Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)
2. Run `docker compose build --pull --no-cache` to build fresh images
3. Run `docker compose up` (the logs will be displayed in the current shell) <br />
   Run `docker compose up -d` (so with -d) to detach from the compose process. <br />
   You can view the logs in Docker Desktop by opening "Containers" -> "php-1" -> "Actions" -> "View Details"
4. Wait for the logs to show "NOTICE: ready to handle connection"
5. Open `https://localhost/ServerManager` in your favorite web browser to open up the Server manager
6. Run `docker compose down --remove-orphans` to stop the Docker containers.

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

## Sponsors

Thanks to all of them for their great support:

- Blackfire.io, the profiling and monitoring service for PHP

# Using Xdebug

The default development image is shipped with [Xdebug](https://xdebug.org/),
a popular debugger and profiler for PHP.

Because it has a significant performance overhead, the step-by-step debugger is disabled by default.
It can be enabled by setting the `XDEBUG_MODE` environment variable to `debug`.

On Linux and Mac:

```
XDEBUG_MODE=debug docker compose up -d
```

On Windows:

```
set XDEBUG_MODE=debug&& docker compose up -d&set XDEBUG_MODE=
```

## Debugging with Xdebug and PHPStorm

First, [create a PHP debug remote server configuration](https://www.jetbrains.com/help/phpstorm/creating-a-php-debug-server-configuration.html):

1. In the `Settings/Preferences` dialog, go to `PHP | Servers`
2. Create a new server:
   * Name: `symfony` (or whatever you want to use for the variable `PHP_IDE_CONFIG`)
   * Host: `localhost` (or the one defined using the `SERVER_NAME` environment variable)
   * Port: `443`
   * Debugger: `Xdebug`
   * Check `Use path mappings`
   * Absolute path on the server: `/srv/app`

You can now use the debugger!

1. In PHPStorm, open the `Run` menu and click on `Start Listening for PHP Debug Connections`
2. Add the `XDEBUG_SESSION=PHPSTORM` query parameter to the URL of the page you want to debug, or use [other available triggers](https://xdebug.org/docs/step_debug#activate_debugger)

    Alternatively, you can use [the **Xdebug extension**](https://xdebug.org/docs/step_debug#browser-extensions) for your preferred web browser. 

3. On command line, we might need to tell PHPStorm which [path mapping configuration](https://www.jetbrains.com/help/phpstorm/zero-configuration-debugging-cli.html#configure-path-mappings) should be used, set the value of the PHP_IDE_CONFIG environment variable to `serverName=symfony`, where `symfony` is the name of the debug server configured higher.

    Example:

    ```console
    XDEBUG_SESSION=1 PHP_IDE_CONFIG="serverName=symfony" php bin/console ...
    ```

## Troubleshooting

Inspect the installation with the following command. The Xdebug version should be displayed.

```console
$ docker compose exec php php --version

PHP ...
    with Xdebug v3.x.x ...
```