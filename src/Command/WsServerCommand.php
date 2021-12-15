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

    protected static $defaultName = 'app:ws-server';

    private WsServer $wsServer;

    public function __construct(WsServer $wsServer)
    {
        $this->wsServer = $wsServer;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Run the websocket server for MSP Challenge')
            ->addOption(
                self::OPTION_PORT,
                'p',
                InputOption::VALUE_REQUIRED,
                'the server port to use',
                '8080'
            )
            ->addOption(
                self::OPTION_GAME_SESSION_ID,
                's',
                InputOption::VALUE_OPTIONAL,
                'only clients with this Game session ID will be allowed keep a connection'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption(self::OPTION_GAME_SESSION_ID) != null) {
            $this->wsServer->setGameSessionId($input->getOption(self::OPTION_GAME_SESSION_ID));
        }

        // the console helper will handle the console output using events dispatched by the wsServer
        /** @var ConsoleOutput $output */
        $consoleHelper = new WsServerConsoleHelper($this->wsServer, $output);

        $server = IoServer::factory(
            new HttpServer(new \Ratchet\WebSocket\WsServer($this->wsServer)),
            $input->getOption(self::OPTION_PORT)
        );
        $this->wsServer->registerLoop($server->loop);
        $server->run();
        return Command::SUCCESS;
    }
}
