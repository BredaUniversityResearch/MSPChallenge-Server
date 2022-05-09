<?php

namespace App\Command;

use App\Domain\Services\SymfonyToLegacyHelper;
use App\Domain\WsServer\WsServer;
use App\Domain\WsServer\WsServerConsoleHelper;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class WsServerCommand extends Command
{
    const OPTION_PORT = 'port';
    const OPTION_ADDRESS = 'address';
    const OPTION_GAME_SESSION_ID = 'game-session-id';
    const OPTION_FIXED_TERMINAL_HEIGHT = 'fixed-terminal-height';
    const OPTION_TABLE_OUTPUT = 'table-output';
    const OPTION_MESSAGE_MAX_LINES = 'message-max-lines';
    const OPTION_MESSAGE_FILTER = 'message-filter';

    protected static $defaultName = 'app:ws-server';

    private WsServer $wsServer;
    private string $projectDir;

    public function __construct(
        WsServer $wsServer,
        string $projectDir,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $helper
    ) {
        $this->wsServer = $wsServer;
        $this->projectDir = $projectDir;
        parent::__construct();
    }

    protected function configure(): void
    {
        require($this->projectDir . '/ServerManager/config.php');
        $this
            ->setDescription('Run the websocket server for MSP Challenge')
            ->addOption(
                self::OPTION_PORT,
                'p',
                InputOption::VALUE_REQUIRED,
                'the server port to use',
                $GLOBALS['config']['ws_server']['port'] ?: 45001
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
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        define('WSS', 1); // to identify that we are in a websocket server instance. Value is not important.
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

        if (null != $input->getOption(self::OPTION_GAME_SESSION_ID)) {
            $this->wsServer->setGameSessionId($input->getOption(self::OPTION_GAME_SESSION_ID));
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
        sapi_windows_set_ctrl_handler(function (int $event) use ($server) {
            switch ($event) {
                case PHP_WINDOWS_EVENT_CTRL_C:
                    $server->loop->stop();
                    break;
            }
        });
        $server->run();

        return Command::SUCCESS;
    }
}
