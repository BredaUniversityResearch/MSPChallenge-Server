<?php

namespace App\Command;

use App\Domain\Services\SymfonyToLegacyHelper;
use App\Domain\WsServer\Plugins\BootstrapWsServerPlugin;
use App\Domain\WsServer\Plugins\PluginHelper;
use App\Domain\WsServer\WsServer;
use App\Domain\WsServer\WsServerConsoleHelper;
use App\Domain\WsServer\WsServerOutput;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:ws-server',
    description: 'Run the websocket server for MSP Challenge',
)]
class WsServerCommand extends Command
{
    const OPTION_PORT = 'port';
    const OPTION_ADDRESS = 'address';
    const OPTION_GAME_SESSION_ID = 'game-session-id';
    const OPTION_FIXED_TERMINAL_HEIGHT = 'fixed-terminal-height';
    const OPTION_TABLE_OUTPUT = 'table-output';
    const OPTION_MESSAGE_MAX_LINES = 'message-max-lines';
    const OPTION_MESSAGE_FILTER = 'message-filter';
    const OPTION_SERVER_ID = 'server-id';

    protected static $defaultName = 'app:ws-server';

    private WsServer $wsServer;

    public function __construct(
        WsServer $wsServer,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $helper,
        PluginHelper $pluginHelper
    ) {
        $this->wsServer = $wsServer;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPTION_PORT,
                'p',
                InputOption::VALUE_REQUIRED,
                'the server port to use',
                (int)($_ENV['WS_SERVER_PORT'] ?? 45001)
            )
            ->addOption(
                self::OPTION_ADDRESS,
                'a',
                InputOption::VALUE_REQUIRED,
                'the server address to use',
                '0.0.0.0'
            )
            ->addOption(
                self::OPTION_GAME_SESSION_ID,
                's',
                InputOption::VALUE_REQUIRED,
                'only clients with this Game session ID will be allowed keep a connection'
            )
            ->addOption(
                self::OPTION_FIXED_TERMINAL_HEIGHT,
                null,
                InputOption::VALUE_REQUIRED,
                'fixed terminal height, the number of rows allowed'
            )
            ->addOption(
                self::OPTION_TABLE_OUTPUT,
                't',
                InputOption::VALUE_NONE,
                'enable client connections table output with statistics'
            )
            ->addOption(
                self::OPTION_MESSAGE_MAX_LINES,
                'l',
                InputOption::VALUE_REQUIRED,
                'the maximum number of lines for each message'
            )
            ->addOption(
                self::OPTION_MESSAGE_FILTER,
                'f',
                InputOption::VALUE_REQUIRED,
                'only show messages containing this text'
            )
            ->addOption(
                self::OPTION_SERVER_ID,
                'i',
                InputOption::VALUE_REQUIRED,
                'the server identifier'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
//        // note (MH): handy, enable to catch deprecations/notices/warnings/errors to log
//        set_error_handler(function (
//            int $errno,
//            string $errstr,
//            string $errfile,
//            int $errline
//        ) {
//            file_put_contents(
//                $this->projectDir . '/var/log/deprecations.log',
//                $errstr . PHP_EOL . $errfile . '@' . $errline . PHP_EOL . PHP_EOL,
//                FILE_APPEND
//            );
//        });

        define('WSS', 1); // to identify that we are in a websocket server instance. Value is not important.

        WsServerOutput::setVerbosity($output->getVerbosity());
        WsServerOutput::setMessageFilter($input->getOption(self::OPTION_MESSAGE_FILTER));
        if (null !== $gameSessionId = $input->getOption(self::OPTION_GAME_SESSION_ID)) {
            $this->wsServer->setGameSessionIdFilter($gameSessionId);
        }
        if (null !== $serverId = $input->getOption(self::OPTION_SERVER_ID)) {
            $this->wsServer->setId($serverId);
        }

        // the console helper will handle console output using events dispatched by the wsServer
        /** @var ConsoleOutput $output */
        $consoleHelper = new WsServerConsoleHelper(
            $this->wsServer,
            $output,
            $input->getOption(self::OPTION_TABLE_OUTPUT),
            $input->getOption(self::OPTION_MESSAGE_MAX_LINES),
            $input->getOption(self::OPTION_MESSAGE_FILTER),
        );
        $consoleHelper->setTerminalHeight($input->getOption(self::OPTION_FIXED_TERMINAL_HEIGHT));

        $server = IoServer::factory(
            new HttpServer(new \Ratchet\WebSocket\WsServer($this->wsServer)),
            $input->getOption(self::OPTION_PORT),
            $input->getOption(self::OPTION_ADDRESS)
        );
        $this->wsServer->registerLoop($server->loop);

        // plugins
        $this->wsServer->registerPlugin(new BootstrapWsServerPlugin($input->getOption(self::OPTION_TABLE_OUTPUT)));

        if (function_exists('\sapi_windows_set_ctrl_handler')) {
            \sapi_windows_set_ctrl_handler(function (int $event) use ($server) {
                if ($event == PHP_WINDOWS_EVENT_CTRL_C) {
                    $server->loop->stop();
                }
            });
        }
        if (function_exists('\pcntl_signal')) {
            \pcntl_signal(SIGTERM, function (int $sigNo) use ($server) {
                if ($sigNo == SIGTERM) {
                    $server->loop->stop();
                }
            });
        }
        $server->run();

        return Command::SUCCESS;
    }
}
