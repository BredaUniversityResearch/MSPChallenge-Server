<?php

namespace App\Command;

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
    const OPTION_GAME_SESSION_ID = 'game-session-id';
    const OPTION_FIXED_TERMINAL_HEIGHT = 'fixed-terminal-height';
    const OPTION_TABLE_OUTPUT = 'table-output';

    protected static $defaultName = 'app:ws-server';

    private WsServer $wsServer;
    private string $projectDir;

    public function __construct(WsServer $wsServer, string $projectDir)
    {
        $this->wsServer = $wsServer;
        $this->projectDir = $projectDir;
        parent::__construct();
    }

    protected function configure(): void
    {
        require($this->projectDir . '/ServerManager/config.php');
        $this
            ->setHelp('Run the websocket server for MSP Challenge')
            ->addOption(
                self::OPTION_PORT,
                'p',
                InputOption::VALUE_REQUIRED,
                'the server port to use',
                $GLOBALS['config']['ws_server']['port'] ?: 45001
            )
            ->addOption(
                self::OPTION_GAME_SESSION_ID,
                's',
                InputOption::VALUE_REQUIRED,
                'only clients with this Game session ID will be allowed keep a connection'
            )
            ->addOption(
                self::OPTION_FIXED_TERMINAL_HEIGHT,
                'f',
                InputOption::VALUE_REQUIRED,
                'fixed terminal height, the number of rows allowed'
            )
            ->addOption(
                self::OPTION_TABLE_OUTPUT,
                't',
                InputOption::VALUE_NONE,
                'enable client connections table output with statistics'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null != $input->getOption(self::OPTION_GAME_SESSION_ID)) {
            $this->wsServer->setGameSessionId($input->getOption(self::OPTION_GAME_SESSION_ID));
        }

        // the console helper will handle console output using events dispatched by the wsServer
        /** @var ConsoleOutput $output */
        $consoleHelper = new WsServerConsoleHelper(
            $this->wsServer,
            $output,
            $input->getOption(self::OPTION_TABLE_OUTPUT)
        );
        $consoleHelper->setTerminalHeight($input->getOption(self::OPTION_FIXED_TERMINAL_HEIGHT));

        $server = IoServer::factory(
            new HttpServer(new \Ratchet\WebSocket\WsServer($this->wsServer)),
            $input->getOption(self::OPTION_PORT)
        );
        $this->wsServer->registerLoop($server->loop);
        $server->run();

        return Command::SUCCESS;
    }
}
